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
     * Émet un devis (DRAFT → ISSUED)
     * 
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function issue(Quote $quote): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('QUOTE_ISSUE', $quote)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission d\'émettre ce devis.');
        }

        // Vérifier que la transition est possible
        if (!$quote->getStatut()?->canBeIssued()) {
            throw new \RuntimeException(
                sprintf(
                    'Le devis ne peut pas être émis depuis l\'état "%s".',
                    $quote->getStatut()?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Valider que le devis est prêt à être émis
        $this->validateBeforeSend($quote);

        // Générer et sauvegarder le PDF AVANT de changer le statut
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

        // Effectuer la transition
        $oldStatus = $quote->getStatut();
        $quote->setStatut(QuoteStatus::ISSUED);

        // Enregistrer l'audit
        $this->logStatusChange($quote, $oldStatus, QuoteStatus::ISSUED, 'issue');

        // Persister tout en une seule fois
        $this->entityManager->flush();

        $this->logger->info('Devis émis', [
            'quote_id' => $quote->getId(),
            'quote_number' => $quote->getNumero(),
            'old_status' => $oldStatus?->value,
            'new_status' => QuoteStatus::ISSUED->value,
            'pdf_generated' => $quote->getPdfFilename() !== null,
        ]);
    }

    /**
     * Envoie un devis (ISSUED → SENT, ou renvoie si déjà SENT)
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

        // Effectuer la transition seulement si le devis est en ISSUED (ISSUED → SENT)
        $oldStatus = $quote->getStatut();
        if ($oldStatus === QuoteStatus::ISSUED) {
            $quote->setStatut(QuoteStatus::SENT);
            // Enregistrer l'audit pour le changement de statut
            $this->logStatusChange($quote, $oldStatus, QuoteStatus::SENT, 'send');
        } else {
            // Si le devis est déjà envoyé, on ne change pas le statut mais on enregistre juste l'envoi
            $this->logStatusChange($quote, $oldStatus, $oldStatus, 'resend');
        }
        
        // Toujours enregistrer la date d'envoi et incrémenter le compteur
        $quote->setDateEnvoi(new \DateTime());
        $quote->incrementSentCount();
        // Par défaut, le canal est 'email' (peut être modifié plus tard)
        if (!$quote->getDeliveryChannel()) {
            $quote->setDeliveryChannel('email');
        }

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Devis envoyé', [
            'quote_id' => $quote->getId(),
            'quote_number' => $quote->getNumero(),
            'old_status' => $oldStatus?->value,
            'new_status' => QuoteStatus::SENT->value,
        ]);
    }

    /**
     * Accepte un devis (SENT → ACCEPTED)
     * 
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function accept(Quote $quote): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('QUOTE_ACCEPT', $quote)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission d\'accepter ce devis.');
        }

        // Vérifier que la transition est possible
        if (!$quote->getStatut()?->canBeAccepted()) {
            throw new \RuntimeException(
                sprintf(
                    'Le devis ne peut pas être accepté depuis l\'état "%s".',
                    $quote->getStatut()?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Effectuer la transition
        $oldStatus = $quote->getStatut();
        $quote->setStatut(QuoteStatus::ACCEPTED);
        $quote->setDateAcceptation(new \DateTime());

        // Enregistrer l'audit
        $this->logStatusChange($quote, $oldStatus, QuoteStatus::ACCEPTED, 'accept');

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Devis accepté', [
            'quote_id' => $quote->getId(),
            'quote_number' => $quote->getNumero(),
            'old_status' => $oldStatus?->value,
            'new_status' => QuoteStatus::ACCEPTED->value,
        ]);
    }

    /**
     * Signe un devis (SENT/ACCEPTED → SIGNED)
     * 
     * C'est l'action la plus importante : le devis devient un CONTRAT
     * Après signature, le devis devient immuable.
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
        if ($reason !== null) {
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
}

