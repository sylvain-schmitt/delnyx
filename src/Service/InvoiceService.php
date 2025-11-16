<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\InvoiceStatus;
use App\Entity\Quote;
use App\Entity\QuoteStatus;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Bundle\SecurityBundle\Security;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Service pour gérer les transitions d'état et les opérations métier sur les factures
 * 
 * Ce service centralise toute la logique métier liée aux factures :
 * - Transitions d'état (issue, markPaid)
 * - Validation des règles métier
 * - Audit et traçabilité
 * - Génération depuis devis
 * - Gestion des avoirs
 * 
 * @package App\Service
 */
class InvoiceService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
        private readonly InvoiceNumberGenerator $numberGenerator,
        private readonly MailerInterface $mailer,
    ) {
    }

    /**
     * Injection optionnelle d'AuditService (pour éviter la dépendance circulaire)
     */
    private ?AuditService $auditService = null;

    public function setAuditService(AuditService $auditService): void
    {
        $this->auditService = $auditService;
    }

    /**
     * Émet une facture (DRAFT → ISSUED)
     * 
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function issue(Invoice $invoice): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('INVOICE_ISSUE', $invoice)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission d\'émettre cette facture.');
        }

        // Vérifier que la transition est possible
        $statutEnum = $invoice->getStatutEnum();
        if (!$statutEnum || !$statutEnum->canBeIssued()) {
            throw new \RuntimeException(
                sprintf(
                    'La facture ne peut pas être émise depuis l\'état "%s".',
                    $statutEnum?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Valider que la facture peut être émise
        $invoice->validateCanBeIssued();

        // Générer le numéro si nécessaire (avec verrou)
        if (!$invoice->getNumero()) {
            $numero = $this->numberGenerator->generate($invoice);
            $invoice->setNumero($numero);
        }

        // Effectuer la transition
        $oldStatus = $statutEnum;
        $invoice->setStatutEnum(InvoiceStatus::ISSUED);
        $invoice->setDateEnvoi(new \DateTime());

        // Enregistrer l'audit
        $this->logStatusChange($invoice, $oldStatus, InvoiceStatus::ISSUED, 'issue');

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Facture émise', [
            'invoice_id' => $invoice->getId(),
            'invoice_number' => $invoice->getNumero(),
            'old_status' => $oldStatus?->value,
            'new_status' => InvoiceStatus::ISSUED->value,
        ]);

        // TODO: Planifier génération PDF + hash SHA256
    }

    /**
     * Envoie une facture au client (ISSUED ou PAID → SEND)
     * 
     * Peut être fait plusieurs fois (relances)
     * Envoie par email et/ou PDP selon la configuration
     * 
     * @param string|null $channel Canal d'envoi ('email', 'pdp', 'both'). Si null, utilise 'email' par défaut
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la facture ne peut pas être envoyée
     */
    public function send(Invoice $invoice, ?string $channel = 'email'): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('INVOICE_SEND', $invoice)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission d\'envoyer cette facture.');
        }

        // Vérifier que la facture peut être envoyée (doit être émise)
        $statutEnum = $invoice->getStatutEnum();
        if (!$statutEnum || !$statutEnum->canBeSent()) {
            throw new \RuntimeException(
                sprintf(
                    'La facture ne peut pas être envoyée depuis l\'état "%s". Seules les factures émises peuvent être envoyées.',
                    $statutEnum?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Vérifier que le client a une adresse email
        if (!$invoice->getClient() || !$invoice->getClient()->getEmail()) {
            throw new \RuntimeException('Le client doit avoir une adresse email pour envoyer la facture.');
        }

        $channels = [];
        $deliveryChannel = null;

        // Envoi par email (toujours disponible)
        if ($channel === 'email' || $channel === 'both') {
            try {
                $this->sendByEmail($invoice);
                $channels[] = 'email';
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de l\'envoi de la facture par email', [
                    'invoice_id' => $invoice->getId(),
                    'error' => $e->getMessage(),
                ]);
                throw new \RuntimeException('Erreur lors de l\'envoi par email : ' . $e->getMessage());
            }
        }

        // Envoi via PDP (si activé et demandé)
        if ($channel === 'pdp' || $channel === 'both') {
            // TODO: Implémenter l'envoi via PDP
            // Pour l'instant, on log juste
            $this->logger->info('Envoi PDP demandé (non implémenté)', [
                'invoice_id' => $invoice->getId(),
            ]);
            // $this->sendByPDP($invoice);
            // $channels[] = 'pdp';
        }

        // Déterminer le canal de livraison
        if (count($channels) > 1) {
            $deliveryChannel = 'both';
        } elseif (count($channels) === 1) {
            $deliveryChannel = $channels[0];
        }

        // Mettre à jour les métadonnées d'envoi
        $invoice->setDateEnvoi(new \DateTime());
        $invoice->incrementSentCount();
        if ($deliveryChannel) {
            $invoice->setDeliveryChannel($deliveryChannel);
        }

        // Changer le statut si la facture est ISSUED (ISSUED → SENT)
        $oldStatus = $invoice->getStatutEnum();
        if ($invoice->getStatutEnum() === InvoiceStatus::ISSUED) {
            $invoice->setStatutEnum(InvoiceStatus::SENT);
        }

        // Enregistrer l'audit
        $this->logStatusChange($invoice, $oldStatus, $invoice->getStatutEnum(), 'send', [
            'channel' => $deliveryChannel,
            'recipient' => $invoice->getClient()->getEmail(),
            'sent_count' => $invoice->getSentCount(),
        ]);

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Facture envoyée', [
            'invoice_id' => $invoice->getId(),
            'invoice_number' => $invoice->getNumero(),
            'channel' => $deliveryChannel,
            'recipient' => $invoice->getClient()->getEmail(),
            'sent_count' => $invoice->getSentCount(),
        ]);
    }

    /**
     * Envoie la facture par email
     */
    private function sendByEmail(Invoice $invoice): void
    {
        $client = $invoice->getClient();
        $clientEmail = $client->getEmail();
        $clientName = $client->getNomComplet();

        // TODO: Générer le PDF si nécessaire
        // Pour l'instant, on envoie juste un email avec les informations
        $email = (new Email())
            ->from(new Address('factures@delnyx.fr', 'Delnyx - Facturation'))
            ->to(new Address($clientEmail, $clientName))
            ->subject(sprintf('Facture %s - %s', $invoice->getNumero(), $invoice->getClient()->getNomComplet()))
            ->html($this->renderInvoiceEmail($invoice));

        $this->mailer->send($email);
    }

    /**
     * Rend le template email pour la facture
     * TODO: Créer un vrai template Twig
     */
    private function renderInvoiceEmail(Invoice $invoice): string
    {
        return sprintf(
            '<html><body>
                <h1>Facture %s</h1>
                <p>Bonjour %s,</p>
                <p>Veuillez trouver ci-joint votre facture n°%s d\'un montant de %s.</p>
                <p>Date d\'échéance : %s</p>
                <p>Cordialement,<br>L\'équipe Delnyx</p>
            </body></html>',
            $invoice->getNumero(),
            $invoice->getClient()->getNomComplet(),
            $invoice->getNumero(),
            $invoice->getMontantTTCFormate(),
            $invoice->getDateEcheance()?->format('d/m/Y') ?? 'N/A'
        );
    }

    /**
     * Marque une facture comme payée (ISSUED → PAID)
     * 
     * @param float|null $amount Montant payé (null = paiement total)
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function markPaid(Invoice $invoice, ?float $amount = null): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('INVOICE_MARK_PAID', $invoice)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission de marquer cette facture comme payée.');
        }

        // Vérifier que la transition est possible
        $statutEnum = $invoice->getStatutEnum();
        if (!$statutEnum || !$statutEnum->canBeMarkedPaid()) {
            throw new \RuntimeException(
                sprintf(
                    'La facture ne peut pas être marquée comme payée depuis l\'état "%s".',
                    $statutEnum?->getLabel() ?? 'inconnu'
                )
            );
        }

        $montantTTC = (float) $invoice->getMontantTTC();
        $montantPaye = $amount ?? $montantTTC;

        // Vérifier que le montant payé ne dépasse pas le montant total
        if ($montantPaye > $montantTTC) {
            throw new \RuntimeException(
                sprintf(
                    'Le montant payé (%.2f €) ne peut pas dépasser le montant total (%.2f €).',
                    $montantPaye,
                    $montantTTC
                )
            );
        }

        // Enregistrer le paiement
        $invoice->setDatePaiement(new \DateTime());

        // Si paiement total, passer à PAID
        if (abs($montantPaye - $montantTTC) < 0.01) {
            $oldStatus = $statutEnum;
            $invoice->setStatutEnum(InvoiceStatus::PAID);
            
            // Enregistrer l'audit
            $this->logStatusChange($invoice, $oldStatus, InvoiceStatus::PAID, 'mark_paid');
        }
        // TODO: Gérer les paiements partiels avec une entité Payment

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Facture marquée comme payée', [
            'invoice_id' => $invoice->getId(),
            'invoice_number' => $invoice->getNumero(),
            'amount_paid' => $montantPaye,
            'total_amount' => $montantTTC,
        ]);
    }

    /**
     * Crée une facture à partir d'un devis signé
     * 
     * @param Quote $quote Le devis signé
     * @param bool $issueImmediately Si true, émet la facture immédiatement
     * @return Invoice La facture créée
     * @throws AccessDeniedException si le devis n'est pas signé
     * @throws \RuntimeException si une facture existe déjà pour ce devis
     */
    public function createFromQuote(Quote $quote, bool $issueImmediately = false): Invoice
    {
        // Vérifier que le devis est signé
        if ($quote->getStatut() !== QuoteStatus::SIGNED) {
            throw new AccessDeniedException(
                'Une facture ne peut être créée qu\'à partir d\'un devis signé.'
            );
        }

        // Vérifier qu'il n'y a pas déjà une facture pour ce devis
        if ($quote->getInvoice() !== null) {
            throw new \RuntimeException(
                sprintf(
                    'Une facture existe déjà pour le devis %s.',
                    $quote->getNumero()
                )
            );
        }

        // Créer la facture
        $invoice = new Invoice();
        $invoice->setClient($quote->getClient());
        $invoice->setQuote($quote);
        $invoice->setCompanyId($quote->getCompanyId());
        $invoice->setStatutEnum(InvoiceStatus::DRAFT);
        
        // Copier les montants
        $invoice->setMontantHT($quote->getMontantHT());
        $invoice->setMontantTVA($quote->getMontantTVA());
        $invoice->setMontantTTC($quote->getMontantTTC());
        
        // Copier les conditions
        $invoice->setConditionsPaiement($quote->getConditionsPaiement());
        // Note: Quote n'a pas de delaiPaiement, on laisse la valeur par défaut de Invoice
        $invoice->setNotes($quote->getNotes());
        
        // Définir la date d'échéance (30 jours par défaut si non définie)
        if ($quote->getDateValidite()) {
            $invoice->setDateEcheance($quote->getDateValidite());
        } else {
            $dateEcheance = new \DateTime();
            $dateEcheance->modify('+30 days');
            $invoice->setDateEcheance($dateEcheance);
        }

        // Copier les lignes du devis
        foreach ($quote->getLines() as $quoteLine) {
            $invoiceLine = new \App\Entity\InvoiceLine();
            $invoiceLine->setDescription($quoteLine->getDescription());
            $invoiceLine->setQuantity($quoteLine->getQuantity());
            $invoiceLine->setUnitPrice($quoteLine->getUnitPrice());
            $invoiceLine->setTvaRate($quoteLine->getTvaRate() ?? $quote->getTauxTVA());
            $invoiceLine->recalculateTotalHt();
            $invoice->addLine($invoiceLine);
        }

        // Copier les lignes des avenants signés
        foreach ($quote->getAmendments() as $amendment) {
            if ($amendment->getStatutEnum()?->value === 'signed') {
                foreach ($amendment->getLines() as $amendmentLine) {
                    $invoiceLine = new \App\Entity\InvoiceLine();
                    $invoiceLine->setDescription($amendmentLine->getDescription());
                    $invoiceLine->setQuantity($amendmentLine->getQuantity() ?? 1);
                    $invoiceLine->setUnitPrice($amendmentLine->getUnitPrice());
                    $invoiceLine->setTvaRate($amendmentLine->getTvaRate() ?? $quote->getTauxTVA());
                    $invoiceLine->recalculateTotalHt();
                    $invoice->addLine($invoiceLine);
                }
            }
        }

        // Recalculer les totaux
        $invoice->recalculateTotalsFromLines();

        // Persister
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        // Si demandé, émettre immédiatement
        if ($issueImmediately) {
            $this->issue($invoice);
        }

        $this->logger->info('Facture créée depuis devis', [
            'invoice_id' => $invoice->getId(),
            'quote_id' => $quote->getId(),
            'quote_number' => $quote->getNumero(),
        ]);

        return $invoice;
    }

    /**
     * Enregistre un changement de statut dans l'audit
     */
    private function logStatusChange(
        Invoice $invoice,
        ?InvoiceStatus $oldStatus,
        InvoiceStatus $newStatus,
        string $action,
        array $metadata = []
    ): void {
        if ($this->auditService === null) {
            return; // AuditService non injecté
        }

        $user = $this->security->getUser();
        $userId = $user instanceof User ? $user->getId() : null;

        $this->auditService->log(
            entityType: 'Invoice',
            entityId: $invoice->getId() ?? 0,
            action: $action,
            oldValue: ['statut' => $oldStatus?->value],
            newValue: ['statut' => $newStatus->value],
            userId: $userId,
            metadata: $metadata
        );
    }

    /**
     * Enregistre une action dans l'audit (pour les actions non liées à un changement de statut)
     */
    private function logAction(Invoice $invoice, string $action, array $metadata = []): void
    {
        if ($this->auditService === null) {
            return; // AuditService non injecté
        }

        $user = $this->security->getUser();
        $userId = $user instanceof User ? $user->getId() : null;

        $this->auditService->log(
            entityType: 'Invoice',
            entityId: $invoice->getId() ?? 0,
            action: $action,
            oldValue: null,
            newValue: null,
            userId: $userId,
            metadata: $metadata
        );
    }
}

