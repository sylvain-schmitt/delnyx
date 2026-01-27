<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Quote;
use App\Entity\QuoteStatus;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Bundle\SecurityBundle\Security;
use Psr\Log\LoggerInterface;

/**
 * Service pour gérer les transitions d'état et les opérations métier sur les devis
 *
 * Ce service centralise toute la logique métier liée aux devis :
 * - Transitions d'état (send, accept, sign, cancel, refuse)
 * - Validation des règles métier
 * - Audit et traçabilité
 * - Génération de factures (via InvoiceService)
 *
 * @package App\Service
 */
class QuoteService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
        private readonly PdfGeneratorService $pdfGeneratorService,
        private readonly ?DepositService $depositService = null,
        private readonly ?MagicLinkService $magicLinkService = null,
        private readonly ?EmailService $emailService = null,
    ) {}

    /**
     * Injection optionnelle d'AuditService (pour éviter la dépendance circulaire)
     */
    private ?AuditService $auditService = null;

    public function setAuditService(AuditService $auditService): void
    {
        $this->auditService = $auditService;
    }

    /**
     * Envoie un devis (DRAFT → SENT, ou renvoie si déjà SENT)
     *
     * Workflow simplifié : DRAFT peut être envoyé directement
     *
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function send(Quote $quote): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('QUOTE_SEND', $quote)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission d\'envoyer ce devis.');
        }

        // Vérifier que la transition est possible
        if (!$quote->getStatut()?->canBeSent()) {
            throw new \RuntimeException(
                sprintf(
                    'Le devis ne peut pas être envoyé depuis l\'état "%s".',
                    $quote->getStatut()?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Valider que le devis est prêt à être envoyé
        $this->validateBeforeSend($quote);

        // Gérer la transition selon l'état actuel
        $oldStatus = $quote->getStatut();

        if ($oldStatus === QuoteStatus::DRAFT) {
            // Workflow simplifié : DRAFT → SENT directement
            $quote->setStatut(QuoteStatus::SENT);

            // Générer le PDF si pas encore fait
            if (!$quote->getPdfFilename()) {
                try {
                    $pdfResult = $this->pdfGeneratorService->generateDevisPdf($quote, true);
                    $quote->setPdfFilename($pdfResult['filename']);
                    $quote->setPdfHash($pdfResult['hash']);
                } catch (\Exception $e) {
                    $this->logger->error('Erreur lors de la génération du PDF pour le devis', [
                        'quote_id' => $quote->getId(),
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->logStatusChange($quote, $oldStatus, QuoteStatus::SENT, 'send');
        } elseif ($oldStatus === QuoteStatus::SENT) {
            // Déjà envoyé : simple renvoi, pas de changement de statut
            $this->logStatusChange($quote, $oldStatus, $oldStatus, 'resend');
        }

        // Toujours enregistrer la date d'envoi et incrémenter le compteur
        $quote->setDateEnvoi(new \DateTime());
        $quote->incrementSentCount();

        // Par défaut, le canal est 'email'
        if (!$quote->getDeliveryChannel()) {
            $quote->setDeliveryChannel('email');
        }

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Devis envoyé', [
            'quote_id' => $quote->getId(),
            'quote_number' => $quote->getNumero(),
            'old_status' => $oldStatus?->value,
            'new_status' => $quote->getStatut()->value,
        ]);
    }
    /**
     * Signe un devis (SENT → SIGNED)
     *
     * C'est l'action la plus importante : le devis devient un CONTRAT
     * Après signature, le devis devient immuable.
     *
     * Note: Dans le workflow simplifié, "accepter" = "signer" (pas de statut ACCEPTED séparé)
     *
     * @param string|null $signatureClient Signature électronique du client (optionnel)
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function sign(Quote $quote, ?string $signatureClient = null): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('QUOTE_SIGN', $quote)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission de signer ce devis.');
        }

        // Vérifier que la transition est possible
        if (!$quote->getStatut()?->canBeSigned()) {
            throw new \RuntimeException(
                sprintf(
                    'Le devis ne peut pas être signé depuis l\'état "%s".',
                    $quote->getStatut()?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Valider que le devis peut être signé
        $quote->validateCanBeSigned();

        // Effectuer la transition
        $oldStatus = $quote->getStatut();
        $quote->setStatut(QuoteStatus::SIGNED);
        $quote->setDateSignature(new \DateTime());

        if ($signatureClient !== null) {
            $quote->setSignatureClient($signatureClient);
        }

        // Enregistrer l'utilisateur qui a signé (si disponible)
        $user = $this->security->getUser();
        if ($user instanceof User) {
            // TODO: Ajouter champ signedBy dans Quote si nécessaire
            // $quote->setSignedBy($user);
        }

        // Enregistrer l'audit
        $this->logStatusChange($quote, $oldStatus, QuoteStatus::SIGNED, 'sign');

        // Marquer pour génération PDF (si service disponible)
        // TODO: Appeler PDFService pour générer le PDF signé

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Devis signé - CONTRAT créé', [
            'quote_id' => $quote->getId(),
            'quote_number' => $quote->getNumero(),
            'old_status' => $oldStatus?->value,
            'new_status' => QuoteStatus::SIGNED->value,
            'signed_by' => $user?->getEmail(),
        ]);

        // === AUTOMATISATION ACCOMPTE ===
        // Si le devis a un pourcentage d'acompte > 0, créer automatiquement l'accompte et envoyer l'email
        $acomptePourcentage = (float) $quote->getAcomptePourcentage();

        error_log("=== DEBUG DEPOSIT AUTO ===");
        error_log("Quote ID: " . $quote->getId());
        error_log("Acompte Pourcentage: " . $acomptePourcentage);
        error_log("DepositService available: " . ($this->depositService ? 'YES' : 'NO'));
        error_log("MagicLinkService available: " . ($this->magicLinkService ? 'YES' : 'NO'));
        error_log("EmailService available: " . ($this->emailService ? 'YES' : 'NO'));

        if ($acomptePourcentage > 0) {
            error_log("Condition OK - calling createAndSendDeposit");
            try {
                $this->createAndSendDeposit($quote, $acomptePourcentage);
                error_log("createAndSendDeposit completed successfully");
            } catch (\Exception $e) {
                // Log l'erreur mais ne bloque pas la signature
                error_log("ERROR in createAndSendDeposit: " . $e->getMessage());
                $this->logger->error('Erreur lors de la création automatique de l\'accompte', [
                    'quote_id' => $quote->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            error_log("Condition FAILED - acomptePourcentage <= 0");
        }
    }

    /**
     * Annule un devis (DRAFT/SENT/ACCEPTED → CANCELLED)
     *
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function cancel(Quote $quote, ?string $reason = null): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('QUOTE_CANCEL', $quote)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission d\'annuler ce devis.');
        }

        // Vérifier que la transition est possible
        if (!$quote->getStatut()?->canBeCancelled()) {
            throw new \RuntimeException(
                sprintf(
                    'Le devis ne peut pas être annulé depuis l\'état "%s".',
                    $quote->getStatut()?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Vérifier qu'il n'y a pas déjà une facture générée
        // TODO: Vérifier si une facture existe déjà
        // if ($quote->getInvoice() !== null) {
        //     throw new \RuntimeException('Un devis avec facture ne peut pas être annulé.');
        // }

        // Effectuer la transition
        $oldStatus = $quote->getStatut();
        $quote->setStatut(QuoteStatus::CANCELLED);

        // Enregistrer la raison dans les notes si fournie
        if (!empty($reason)) {
            $currentNotes = $quote->getNotes() ?? '';
            $quote->setNotes(
                ($currentNotes ? $currentNotes . "\n\n" : '') .
                    "Annulation le " . date('d/m/Y H:i') . " : " . $reason
            );
        }

        // Enregistrer l'audit
        $this->logStatusChange($quote, $oldStatus, QuoteStatus::CANCELLED, 'cancel', ['reason' => $reason]);

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Devis annulé', [
            'quote_id' => $quote->getId(),
            'quote_number' => $quote->getNumero(),
            'old_status' => $oldStatus?->value,
            'new_status' => QuoteStatus::CANCELLED->value,
            'reason' => $reason,
        ]);
    }

    /**
     * Refuse un devis (SENT/ACCEPTED → REFUSED)
     *
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function refuse(Quote $quote, ?string $reason = null): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('QUOTE_REFUSE', $quote)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission de refuser ce devis.');
        }

        // Vérifier que la transition est possible
        if (!$quote->getStatut()?->canBeRefused()) {
            throw new \RuntimeException(
                sprintf(
                    'Le devis ne peut pas être refusé depuis l\'état "%s".',
                    $quote->getStatut()?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Effectuer la transition
        $oldStatus = $quote->getStatut();
        $quote->setStatut(QuoteStatus::REFUSED);

        // Enregistrer la raison dans les notes si fournie
        if ($reason !== null) {
            $currentNotes = $quote->getNotes() ?? '';
            $quote->setNotes(
                ($currentNotes ? $currentNotes . "\n\n" : '') .
                    "Refus le " . date('d/m/Y H:i') . " : " . $reason
            );
        }

        // Enregistrer l'audit
        $this->logStatusChange($quote, $oldStatus, QuoteStatus::REFUSED, 'refuse', ['reason' => $reason]);

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Devis refusé', [
            'quote_id' => $quote->getId(),
            'quote_number' => $quote->getNumero(),
            'old_status' => $oldStatus?->value,
            'new_status' => QuoteStatus::REFUSED->value,
            'reason' => $reason,
        ]);
    }

    /**
     * Marque un devis comme expiré si la date de validité est dépassée
     *
     * Cette méthode peut être appelée automatiquement par un cron job
     * ou lors de la lecture d'un devis.
     */
    public function expireIfNeeded(Quote $quote): bool
    {
        // Ne pas expirer si déjà dans un état final
        if ($quote->getStatut()?->isFinal()) {
            return false;
        }

        // Vérifier si la date de validité est dépassée
        if ($quote->getDateValidite() === null) {
            return false;
        }

        $now = new \DateTime();
        if ($quote->getDateValidite() < $now) {
            $oldStatus = $quote->getStatut();
            $quote->setStatut(QuoteStatus::EXPIRED);

            // Enregistrer l'audit
            $this->logStatusChange($quote, $oldStatus, QuoteStatus::EXPIRED, 'expire');

            // Persister
            $this->entityManager->flush();

            $this->logger->info('Devis expiré automatiquement', [
                'quote_id' => $quote->getId(),
                'quote_number' => $quote->getNumero(),
                'date_validite' => $quote->getDateValidite()->format('Y-m-d'),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Valide qu'un devis peut être envoyé
     *
     * @throws \RuntimeException si le devis n'est pas prêt à être envoyé
     */
    private function validateBeforeSend(Quote $quote): void
    {
        // Vérifier qu'au moins une ligne est présente
        if ($quote->getLines()->isEmpty()) {
            throw new \RuntimeException('Un devis ne peut pas être envoyé sans ligne.');
        }

        // Vérifier qu'un client est associé
        if ($quote->getClient() === null) {
            throw new \RuntimeException('Un devis ne peut pas être envoyé sans client.');
        }

        // Vérifier que le montant TTC est positif
        if ((float) $quote->getMontantTTC() <= 0) {
            throw new \RuntimeException('Un devis ne peut pas être envoyé avec un montant TTC négatif ou nul.');
        }
    }

    /**
     * Permet de modifier un devis envoyé en le repassant en DRAFT
     * Utile si le client demande des modifications après envoi
     *
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function backToDraft(Quote $quote): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('QUOTE_EDIT', $quote)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission de modifier ce devis.');
        }

        $currentStatus = $quote->getStatut();

        // Workflow simplifié : retour en DRAFT uniquement depuis SENT
        if ($currentStatus !== QuoteStatus::SENT) {
            throw new \RuntimeException(
                sprintf(
                    'Le devis ne peut pas être repassé en brouillon depuis l\'état "%s". Seuls les devis SENT peuvent être modifiés.',
                    $currentStatus?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Effectuer la transition
        $oldStatus = $quote->getStatut();
        $quote->setStatut(QuoteStatus::DRAFT);

        // Enregistrer l'audit
        $this->logStatusChange($quote, $oldStatus, QuoteStatus::DRAFT, 'back_to_draft', [
            'reason' => 'Modification demandée après envoi'
        ]);

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Devis repassé en brouillon pour modification', [
            'quote_id' => $quote->getId(),
            'quote_number' => $quote->getNumero(),
            'old_status' => $oldStatus?->value,
            'new_status' => QuoteStatus::DRAFT->value,
        ]);
    }

    /**
     * Envoie un email de relance pour un devis envoyé
     * Ne change pas le statut, envoie juste un rappel au client
     *
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la relance n'est pas possible
     */
    public function remind(Quote $quote): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('QUOTE_SEND', $quote)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission de relancer ce devis.');
        }

        // Workflow simplifié : relance uniquement depuis SENT
        if ($quote->getStatut() !== QuoteStatus::SENT) {
            throw new \RuntimeException(
                sprintf(
                    'Le devis ne peut pas être relancé depuis l\'état "%s". Seuls les devis SENT peuvent être relancés.',
                    $quote->getStatut()?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Vérifier qu'un client avec email existe
        if (!$quote->getClient() || !$quote->getClient()->getEmail()) {
            throw new \RuntimeException('Impossible de relancer le devis : aucun email client configuré.');
        }

        // Enregistrer l'action de relance dans l'audit
        $this->logStatusChange(
            $quote,
            $quote->getStatut(),
            $quote->getStatut(),
            'remind',
            [
                'reminder_sent_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'client_email' => $quote->getClient()->getEmail()
            ]
        );

        // Note : L'envoi de l'email de relance sera géré par le controller
        // qui appellera EmailService avec un template spécifique de relance

        $this->logger->info('Relance de devis enregistrée', [
            'quote_id' => $quote->getId(),
            'quote_number' => $quote->getNumero(),
            'status' => $quote->getStatut()?->value,
            'client_email' => $quote->getClient()->getEmail(),
        ]);
    }

    /**
     * Enregistre un changement de statut dans l'audit
     */
    private function logStatusChange(
        Quote $quote,
        ?QuoteStatus $oldStatus,
        QuoteStatus $newStatus,
        string $action,
        array $metadata = []
    ): void {
        if ($this->auditService !== null) {
            $user = $this->security->getUser();
            $this->auditService->log(
                entityType: 'Quote',
                entityId: $quote->getId(),
                action: $action,
                oldValue: ['statut' => $oldStatus?->value],
                newValue: ['statut' => $newStatus->value],
                userId: $user instanceof User ? $user->getId() : null,
                metadata: $metadata
            );
        }
    }

    /**
     * Crée automatiquement un accompte et envoie l'email au client
     */
    private function createAndSendDeposit(Quote $quote, float $percentage): void
    {
        if (!$this->depositService || !$this->magicLinkService || !$this->emailService) {
            $this->logger->warning('Services de deposit non disponibles pour l\'automatisation');
            return;
        }

        // Créer l'accompte
        $deposit = $this->depositService->createDeposit($quote, $percentage);

        // Générer le lien de paiement
        $paymentUrl = $this->magicLinkService->generateDepositPayLink($deposit);

        // Envoyer l'email au client
        $client = $quote->getClient();
        if ($client && $client->getEmail()) {
            $this->emailService->sendDepositRequest($deposit, $quote, $paymentUrl);

            $this->logger->info('Demande d\'accompte envoyée automatiquement', [
                'quote_id' => $quote->getId(),
                'deposit_id' => $deposit->getId(),
                'amount' => $deposit->getAmountInEuros(),
                'client_email' => $client->getEmail(),
            ]);
        }
    }
}
