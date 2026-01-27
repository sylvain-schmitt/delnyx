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
use App\Service\EmailService;

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
        private readonly EmailService $emailService,
        private readonly PdfGeneratorService $pdfGeneratorService,
        private readonly CreditNoteService $creditNoteService,
    ) {}

    /**
     * Injection optionnelle d'AuditService (pour éviter la dépendance circulaire)
     */
    private ?AuditService $auditService = null;
    private ?DepositService $depositService = null;

    public function setAuditService(AuditService $auditService): void
    {
        $this->auditService = $auditService;
    }

    public function setDepositService(DepositService $depositService): void
    {
        $this->depositService = $depositService;
    }

    /**
     * Émet une facture (DRAFT → ISSUED)
     *
     * @param Invoice $invoice
     * @param bool $forceSecurityBypass Si true, ignore les vérifications de permissions (pour usage système/tâche planifiée)
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function issue(Invoice $invoice, bool $forceSecurityBypass = false): void
    {
        // Vérifier les permissions (sauf si bypass forcé)
        if (!$forceSecurityBypass && !$this->authorizationChecker->isGranted('INVOICE_ISSUE', $invoice)) {
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

        // Générer et sauvegarder le PDF AVANT de changer le statut
        // Ceci est crucial pour éviter les problèmes d'immuabilité
        try {
            $pdfResult = $this->pdfGeneratorService->generateFacturePdf($invoice, true);
            $invoice->setPdfFilename($pdfResult['filename']);
            $invoice->setPdfHash($pdfResult['hash']);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération du PDF pour la facture', [
                'invoice_id' => $invoice->getId(),
                'error' => $e->getMessage()
            ]);
            // On continue quand même l'émission, le PDF pourra être régénéré plus tard
        }

        // Effectuer la transition
        $oldStatus = $statutEnum;
        $invoice->setStatutEnum(InvoiceStatus::ISSUED);
        $invoice->setDateEnvoi(new \DateTime());

        // Enregistrer l'audit
        $this->logStatusChange($invoice, $oldStatus, InvoiceStatus::ISSUED, 'issue');

        // Persister tout en une seule fois
        $this->entityManager->flush();

        $this->logger->info('Facture émise', [
            'invoice_id' => $invoice->getId(),
            'invoice_number' => $invoice->getNumero(),
            'old_status' => $oldStatus?->value,
            'new_status' => InvoiceStatus::ISSUED->value,
            'pdf_generated' => $invoice->getPdfFilename() !== null,
        ]);
    }

    /**
     * Émet et envoie une facture en une seule action
     */
    public function issueAndSend(Invoice $invoice, ?string $channel = 'email', bool $forceSecurityBypass = false): void
    {
        // ... (shortcut, implementation delegates)
        $this->issue($invoice, $forceSecurityBypass);
        $this->send($invoice, $channel, $forceSecurityBypass);
    }

    /**
     * Envoie une facture au client
     *
     * @param Invoice $invoice
     * @param string|null $channel
     * @param bool $forceSecurityBypass Si true, ignore les vérifications de permissions
     */
    public function send(Invoice $invoice, ?string $channel = 'email', bool $forceSecurityBypass = false): void
    {
        // Vérifier les permissions
        if (!$forceSecurityBypass && !$this->authorizationChecker->isGranted('INVOICE_SEND', $invoice)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission d\'envoyer cette facture.');
        }

        // Vérifier que la facture peut être envoyée
        $statutEnum = $invoice->getStatutEnum();
        if (!$statutEnum || !$statutEnum->canBeSent()) {
            throw new \RuntimeException(
                sprintf(
                    'La facture ne peut pas être envoyée depuis l\'état "%s".',
                    $statutEnum?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Si DRAFT, émettre automatiquement avant d'envoyer
        if ($statutEnum === InvoiceStatus::DRAFT) {
            // Vérifier la permission d'émission (bypass propagé)
            if (!$forceSecurityBypass && !$this->authorizationChecker->isGranted('INVOICE_ISSUE', $invoice)) {
                throw new AccessDeniedException('Vous n\'avez pas la permission d\'émettre cette facture.');
            }
            $this->issue($invoice, $forceSecurityBypass);
        }

        // Vérifier que le client a une adresse email
        if (!$invoice->getClient() || !$invoice->getClient()->getEmail()) {
            throw new \RuntimeException('Le client doit avoir une adresse email pour envoyer la facture.');
        }

        $channels = [];
        $deliveryChannel = null;

        // Envoi par email (toujours disponible)
        if ($channel === 'email' || $channel === 'both') {
            // L'envoi de l'email est géré par le contrôleur via EmailService
            // Ici on note juste que le canal choisi est l'email
            $channels[] = 'email';
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

        // Changer le statut si nécessaire (ISSUED → SENT)
        $oldStatus = $invoice->getStatutEnum();
        if ($invoice->getStatutEnum() === InvoiceStatus::ISSUED) {
            $invoice->setStatutEnum(InvoiceStatus::SENT);
            // Enregistrer l'audit pour le changement de statut
            $this->logStatusChange($invoice, $oldStatus, InvoiceStatus::SENT, 'send', [
                'channel' => $deliveryChannel,
                'recipient' => $invoice->getClient()->getEmail(),
                'sent_count' => $invoice->getSentCount(),
            ]);
        } elseif ($invoice->getStatutEnum() === InvoiceStatus::SENT) {
            // Si la facture est déjà envoyée, on ne change pas le statut mais on enregistre juste l'envoi (relance)
            $this->logStatusChange($invoice, $oldStatus, $oldStatus, 'resend', [
                'channel' => $deliveryChannel,
                'recipient' => $invoice->getClient()->getEmail(),
                'sent_count' => $invoice->getSentCount(),
            ]);
        }


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
     * Marque une facture comme payée (ISSUED → PAID)
     *
     * @param float|null $amount Montant payé (null = paiement total)
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function markPaid(Invoice $invoice, ?float $amount = null, bool $skipSubscriptions = false): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('INVOICE_MARK_PAID', $invoice)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission de marquer cette facture comme payée.');
        }

        // Vérifier que la transition est possible
        $statutEnum = $invoice->getStatutEnum();
        if (!$statutEnum || !$statutEnum->canBeMarkedPaid()) {
            // Si déjà payée, on ne fait rien (idempotent)
            if ($statutEnum === InvoiceStatus::PAID) {
                return;
            }
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

            // Réinitialiser le PDF pour forcer sa régénération avec le tampon "PAYÉE"
            $invoice->setPdfFilename(null);
            $invoice->setPdfHash(null);

            // Enregistrer l'audit
            $this->logStatusChange($invoice, $oldStatus, InvoiceStatus::PAID, 'mark_paid');

            // Envoyer l'email de confirmation au client
            try {
                if ($invoice->getClient() && $invoice->getClient()->getEmail()) {
                    $this->emailService->sendPaymentConfirmation($invoice, $montantPaye);
                }
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de l\'envoi de l\'email de confirmation de paiement', [
                    'invoice_id' => $invoice->getId(),
                    'error' => $e->getMessage()
                ]);
            }

            // Créer les abonnements manuels si nécessaire
            if (!$skipSubscriptions) {
                $this->createManualSubscriptionFromInvoice($invoice);
            }

            // SYNCHRONISATION ACOMPTE
            if ($invoice->getSourceDeposit()) {
                $deposit = $invoice->getSourceDeposit();
                if ($deposit->getStatus() !== \App\Entity\DepositStatus::PAID) {
                    $deposit->setStatus(\App\Entity\DepositStatus::PAID);
                    $deposit->setPaidAt(new \DateTimeImmutable());
                }
            }
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
     * Marque une facture comme payée via un paiement externe (Stripe, PayPal, etc.)
     *
     * Cette méthode ne vérifie PAS les permissions car le paiement est validé
     * par le système de paiement externe (webhook Stripe ou retour de paiement).
     *
     * @param float|null $amount Montant payé (null = paiement total)
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function markPaidByExternalPayment(Invoice $invoice, ?float $amount = null, bool $skipSubscriptions = false, ?\DateTimeInterface $paymentDate = null): void
    {
        // Vérifier que la transition est possible
        $statutEnum = $invoice->getStatutEnum();
        if (!$statutEnum || !$statutEnum->canBeMarkedPaid()) {
            // Si déjà payée, on ne fait rien (idempotent)
            if ($statutEnum === InvoiceStatus::PAID) {
                return;
            }
            throw new \RuntimeException(
                sprintf(
                    'La facture ne peut pas être marquée comme payée depuis l\'état "%s".',
                    $statutEnum?->getLabel() ?? 'inconnu'
                )
            );
        }

        $montantTTC = (float) $invoice->getMontantTTC();
        $balanceDue = (float) $invoice->getBalanceDue();
        $montantPaye = $amount ?? $balanceDue;

        // Enregistrer le paiement
        $invoice->setDatePaiement($paymentDate ?? new \DateTime());

        // Si paiement total (égal ou supérieur au solde dû), passer à PAID
        if ($montantPaye >= ($balanceDue - 0.01)) {
            $oldStatus = $statutEnum;
            $invoice->setStatutEnum(InvoiceStatus::PAID);

            // Réinitialiser le PDF pour forcer sa régénération avec le tampon "PAYÉE"
            $invoice->setPdfFilename(null);
            $invoice->setPdfHash(null);

            // Enregistrer l'audit
            $this->logStatusChange($invoice, $oldStatus, InvoiceStatus::PAID, 'external_payment');

            // Envoyer l'email de confirmation au client
            try {
                if ($invoice->getClient() && $invoice->getClient()->getEmail()) {
                    $this->emailService->sendPaymentConfirmation($invoice, $montantPaye);
                }
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de l\'envoi de l\'email de confirmation de paiement (externe)', [
                    'invoice_id' => $invoice->getId(),
                    'error' => $e->getMessage()
                ]);
            }

            // Créer les abonnements manuels si nécessaire
            if (!$skipSubscriptions) {
                $this->createManualSubscriptionFromInvoice($invoice);
            }

            // SYNCHRONISATION ACOMPTE
            if ($invoice->getSourceDeposit()) {
                $deposit = $invoice->getSourceDeposit();
                if ($deposit->getStatus() !== \App\Entity\DepositStatus::PAID) {
                    $deposit->setStatus(\App\Entity\DepositStatus::PAID);
                    $deposit->setPaidAt(new \DateTimeImmutable());
                }
            }
        }

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Facture marquée comme payée via paiement externe', [
            'invoice_id' => $invoice->getId(),
            'invoice_number' => $invoice->getNumero(),
            'amount_paid' => $montantPaye,
            'total_amount' => $montantTTC,
        ]);
    }

    /**
     * Annule une facture (DRAFT ou ISSUED → CANCELLED)
     *
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function cancel(Invoice $invoice, ?string $reason = null): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('INVOICE_CANCEL', $invoice)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission d\'annuler cette facture.');
        }

        // Vérifier que la transition est possible
        $statutEnum = $invoice->getStatutEnum();
        if (!$statutEnum || !$statutEnum->canBeCancelled()) {
            throw new \RuntimeException(
                sprintf(
                    'La facture ne peut pas être annulée depuis l\'état "%s".',
                    $statutEnum?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Vérifier explicitement que la facture n'est pas payée
        if ($statutEnum === InvoiceStatus::PAID) {
            throw new \RuntimeException('Une facture payée ne peut pas être annulée. Créez un avoir pour rembourser.');
        }

        // Effectuer la transition
        $oldStatus = $statutEnum;
        $invoice->setStatutEnum(InvoiceStatus::CANCELLED);
        $invoice->setDateModification(new \DateTime());

        // Enregistrer la raison dans les notes si fournie
        if (!empty($reason)) {
            $currentNotes = $invoice->getNotes() ?? '';
            $invoice->setNotes(
                ($currentNotes ? $currentNotes . "\n\n" : '') .
                    "Annulation le " . date('d/m/Y H:i') . " : " . $reason
            );
        }

        // Enregistrer l'audit
        $this->logStatusChange($invoice, $oldStatus, InvoiceStatus::CANCELLED, 'cancel', ['reason' => $reason]);

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Facture annulée', [
            'invoice_id' => $invoice->getId(),
            'invoice_number' => $invoice->getNumero(),
            'old_status' => $oldStatus?->value,
            'new_status' => InvoiceStatus::CANCELLED->value,
            'reason' => $reason,
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
            // Copie des infos d'abonnement
            $invoiceLine->setSubscriptionMode($quoteLine->getSubscriptionMode());
            $invoiceLine->setRecurrenceAmount($quoteLine->getRecurrenceAmount());
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
                    // Note: Les avenants n'ont pas encore de subscriptionMode, on laisse null par défaut
                    // ou on rajoute le champ sur AmendmentLine si nécessaire (hors scope actuel phase 5.4)
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

        // Déduire automatiquement les acomptes payés du devis
        if ($this->depositService !== null) {
            $this->depositService->deductDepositsToInvoice($invoice, $quote);
        }

        // Si demandé, émettre immédiatement
        if ($issueImmediately) {
            $this->issue($invoice);
        }

        $this->logger->info('Facture créée depuis devis', [
            'invoice_id' => $invoice->getId(),
            'quote_id' => $quote->getId(),
            'quote_number' => $quote->getNumero(),
            'deposits_deducted' => $invoice->hasDepositsDeducted(),
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

    /**
     * Crée un abonnement manuel à partir des lignes d'une facture payée
     */
    private function createManualSubscriptionFromInvoice(Invoice $invoice): void
    {
        // Ne pas créer d'abonnement s'il y en a déjà un (ex: Stripe déjà synchro par Webhook)
        if ($invoice->getSubscription() !== null) {
            return;
        }
        foreach ($invoice->getLines() as $line) {
            $subscriptionMode = $line->getSubscriptionMode();

            // Si c'est une ligne d'abonnement (mensuel ou annuel)
            if ($subscriptionMode && in_array($subscriptionMode, ['monthly', 'yearly'])) {

                // Vérifier si un abonnement existe déjà pour ce client et ce tarif (ou libellé similaire)
                // Pour simplifier : on crée un nouvel abonnement à chaque fois pour l'instant
                // IDÉALEMENT : On devrait vérifier si c'est un RENOUVELLEMENT d'un abo existant.
                // TODO: Logique de renouvellement vs création. Ici on suppose création ou ré-abonnement.

                $subscription = new \App\Entity\Subscription();
                $subscription->setClient($invoice->getClient());
                $subscription->setTariff($line->getTariff());
                $subscription->setCustomLabel($line->getDescription());
                $subscription->setIntervalUnit($subscriptionMode === 'monthly' ? 'month' : 'year');

                // On stocke le montant total TTC de la récurrence (pour affichage et futurs renouvellements)
                $tvaRate = (float) ($line->getTvaRate() ?? 0);
                $recurrenceAmount = $line->getRecurrenceAmount() ? ($line->getRecurrenceAmount() * (1 + ($tvaRate / 100))) : $line->getTotalTtc();
                $subscription->setAmount((string) $recurrenceAmount);

                $subscription->setStatus('active');

                // Dates
                $startDate = new \DateTime();
                $subscription->setCurrentPeriodStart($startDate);

                // Date de fin = Date de début + période
                $endDate = clone $startDate;
                if ($subscriptionMode === 'monthly') {
                    $endDate->modify('+1 month');
                } else {
                    $endDate->modify('+1 year');
                }
                $subscription->setCurrentPeriodEnd($endDate);

                // Pas de Stripe ID pour les manuels
                $subscription->setStripeSubscriptionId(null);

                $this->entityManager->persist($subscription);
                $invoice->setSubscription($subscription);

                $this->logger->info('Abonnement manuel créé suite au paiement de la facture', [
                    'invoice_id' => $invoice->getId(),
                    'client_id' => $invoice->getClient()->getId(),
                    'mode' => $subscriptionMode,
                    'amount' => $recurrenceAmount
                ]);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Crée une facture (ou doit déclencher la création d'un avoir) à partir d'un avenant
     */
    public function createFromAmendment(\App\Entity\Amendment $amendment): void
    {
        // 1. Identifier les lignes par direction de Delta
        $hasPositive = false;
        $hasNegative = false;

        foreach ($amendment->getLines() as $line) {
            $delta = (float) $line->getDelta();
            if ($delta > 0.009) {
                $hasPositive = true;
            } elseif ($delta < -0.009) {
                $hasNegative = true;
            }
        }

        // 2. Créer une facture pour les deltas positifs
        if ($hasPositive) {
            $this->createInvoiceFromPositiveAmendment($amendment);
        }

        // 3. Créer un avoir pour les deltas négatifs
        if ($hasNegative) {
            $this->creditNoteService->createFromAmendment($amendment);
        }
    }

    private function createInvoiceFromPositiveAmendment(\App\Entity\Amendment $amendment): void
    {
        $invoice = new Invoice();
        $invoice->setClient($amendment->getQuote()->getClient());
        $invoice->setQuote($amendment->getQuote()); // Lien vers le devis d'origine
        $invoice->setCompanyId($amendment->getCompanyId());
        $invoice->setStatutEnum(InvoiceStatus::DRAFT);
        $invoice->setAmendment($amendment); // Lien OneToOne

        $invoice->setNotes(sprintf(
            "Facture complémentaire suite à l'avenant %s.\n\nMotif : %s",
            $amendment->getNumero(),
            $amendment->getMotif()
        ));
        $invoice->setDateEcheance((new \DateTime())->modify('+30 days'));

        // Créer les lignes
        foreach ($amendment->getLines() as $amendmentLine) {
            // On ne facture que ce qui a augmenté (delta positif)
            if ((float)$amendmentLine->getDelta() <= 0.009) {
                continue;
            }

            $invoiceLine = new \App\Entity\InvoiceLine();
            $invoiceLine->setDescription($amendmentLine->getDescription() . " (Régularisation)");

            // Subtilité : Une ligne d'avenant a Qté, PU, OldValue.
            // Le TotalHT est le Delta.
            // Pour la facture, on veut afficher une ligne simple correspondant au montant dû.
            // On met Qté = 1 et PU = Delta.

            $invoiceLine->setQuantity(1);
            $invoiceLine->setUnitPrice($amendmentLine->getDelta()); // Le montant de la ligne est le delta HT
            $invoiceLine->setTvaRate($amendmentLine->getTvaRate() ?? 20.0);

            $invoiceLine->recalculateTotalHt();
            $invoice->addLine($invoiceLine);
        }

        $invoice->recalculateTotalsFromLines();
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $this->logger->info('Facture de régularisation (Avenant) créée', ['invoice_id' => $invoice->getId()]);
    }
}
