<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CreditNote;
use App\Entity\CreditNoteStatus;
use App\Entity\Invoice;
use App\Entity\InvoiceStatus;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Bundle\SecurityBundle\Security;
use Psr\Log\LoggerInterface;

/**
 * Service pour gérer les transitions d'état et les opérations métier sur les avoirs
 * 
 * Ce service centralise toute la logique métier liée aux avoirs :
 * - Création depuis une facture émise
 * - Transitions d'état (issue, send, cancel)
 * - Validation des règles métier
 * - Audit et traçabilité
 * 
 * @package App\Service
 */
class CreditNoteService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
        private readonly CreditNoteNumberGenerator $numberGenerator,
        private readonly PdfGeneratorService $pdfGeneratorService,
        private readonly \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface $params,
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
     * Crée un avoir à partir d'une facture émise
     * 
     * @throws AccessDeniedException si la facture n'est pas émise
     * @throws \RuntimeException si un avoir total existe déjà
     */
    public function createFromInvoice(Invoice $invoice): CreditNote
    {
        // Vérifier que la facture est émise
        $invoiceStatus = $invoice->getStatutEnum();
        if (!$invoiceStatus || !$invoiceStatus->isEmitted()) {
            throw new AccessDeniedException(
                'Un avoir ne peut être créé que pour une facture émise.'
            );
        }

        // Vérifier que la facture n'est pas annulée
        if ($invoiceStatus === InvoiceStatus::CANCELLED) {
            throw new AccessDeniedException(
                'Un avoir ne peut pas être créé pour une facture annulée.'
            );
        }

        // Créer l'avoir
        $creditNote = new CreditNote();
        $creditNote->setInvoice($invoice);
        $creditNote->setCompanyId($invoice->getCompanyId());
        $creditNote->setStatut(CreditNoteStatus::DRAFT);
        $creditNote->setTauxTVA($invoice->getQuote()?->getTauxTVA() ?? '0.00');

        // Générer le numéro
        if (!$creditNote->getNumber()) {
            $numero = $this->numberGenerator->generate($creditNote);
            $creditNote->setNumber($numero);
        }

        // Persister
        $this->entityManager->persist($creditNote);
        $this->entityManager->flush();

        $this->logger->info('Avoir créé depuis facture', [
            'credit_note_id' => $creditNote->getId(),
            'invoice_id' => $invoice->getId(),
            'invoice_number' => $invoice->getNumero(),
        ]);

        return $creditNote;
    }

    /**
     * Émet un avoir (DRAFT → ISSUED)
     * 
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function issue(CreditNote $creditNote): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('CREDIT_NOTE_ISSUE', $creditNote)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission d\'émettre cet avoir.');
        }

        $status = $creditNote->getStatut();
        if ($status !== CreditNoteStatus::DRAFT) {
            throw new \RuntimeException(
                sprintf(
                    'L\'avoir ne peut pas être émis depuis l\'état "%s".',
                    $status?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Valider que l'avoir peut être émis
        $creditNote->validateCanBeIssued();

        // Générer le numéro si nécessaire
        if (!$creditNote->getNumber()) {
            $numero = $this->numberGenerator->generate($creditNote);
            $creditNote->setNumber($numero);
        }

        // Générer le PDF
        $response = $this->pdfGeneratorService->generateCreditNotePdf($creditNote);
        $pdfContent = $response->getContent();

        // Calculer le hash SHA256
        $hash = hash('sha256', $pdfContent);
        $creditNote->setPdfHash($hash);

        // Sauvegarder le fichier
        $filename = sprintf('avoir-%s-%s.pdf', $creditNote->getNumber(), uniqid());
        $uploadDir = $this->params->get('kernel.project_dir') . '/public/uploads/credit_notes';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        file_put_contents($uploadDir . '/' . $filename, $pdfContent);
        $creditNote->setPdfFilename($filename);

        // Effectuer la transition
        $oldStatus = $status;
        $creditNote->setStatut(CreditNoteStatus::ISSUED);
        $creditNote->setDateEmission(new \DateTime());

        // Enregistrer l'audit
        $this->logStatusChange($creditNote, $oldStatus, CreditNoteStatus::ISSUED, 'issue', [
            'pdf_filename' => $filename,
            'pdf_hash' => $hash
        ]);

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Avoir émis - devient opposable', [
            'credit_note_id' => $creditNote->getId(),
            'credit_note_number' => $creditNote->getNumber(),
            'old_status' => $oldStatus?->value,
            'new_status' => CreditNoteStatus::ISSUED->value,
            'pdf_hash' => $hash
        ]);
    }

    /**
     * Envoie un avoir (ISSUED → SENT, répétable)
     * 
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function send(CreditNote $creditNote): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('CREDIT_NOTE_SEND', $creditNote)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission d\'envoyer cet avoir.');
        }

        $status = $creditNote->getStatut();
        if (!$status || !$status->canBeSent()) {
            throw new \RuntimeException(
                sprintf(
                    'L\'avoir ne peut pas être envoyé depuis l\'état "%s".',
                    $status?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Effectuer la transition seulement si l'avoir est en ISSUED (ISSUED → SENT)
        $oldStatus = $status;
        if ($status === CreditNoteStatus::ISSUED) {
            $creditNote->setStatut(CreditNoteStatus::SENT);
            // Enregistrer l'audit pour le changement de statut
            $this->logStatusChange($creditNote, $oldStatus, CreditNoteStatus::SENT, 'send');
        } else {
            // Si l'avoir est déjà envoyé, on ne change pas le statut mais on enregistre juste l'envoi
            $this->logStatusChange($creditNote, $oldStatus, $oldStatus, 'resend');
        }
        
        // Toujours enregistrer la date d'envoi et incrémenter le compteur
        $creditNote->setSentAt(new \DateTime());
        $creditNote->incrementSentCount();
        // Par défaut, le canal est 'email' (peut être modifié plus tard)
        if (!$creditNote->getDeliveryChannel()) {
            $creditNote->setDeliveryChannel('email');
        }

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Avoir envoyé', [
            'credit_note_id' => $creditNote->getId(),
            'credit_note_number' => $creditNote->getNumber(),
            'old_status' => $oldStatus?->value,
            'new_status' => CreditNoteStatus::SENT->value,
            'sent_count' => $creditNote->getSentCount(),
        ]);
    }

    /**
     * Annule un avoir (DRAFT → CANCELLED ou ISSUED → CANCELLED)
     * 
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function cancel(CreditNote $creditNote, ?string $reason = null): void
    {
        // ... (existing cancel logic)
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('CREDIT_NOTE_CANCEL', $creditNote)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission d\'annuler cet avoir.');
        }

        $status = $creditNote->getStatut();
        if (!$status || !$status->canBeCancelled()) {
            throw new \RuntimeException(
                sprintf(
                    'L\'avoir ne peut pas être annulé depuis l\'état "%s".',
                    $status?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Effectuer la transition
        $oldStatus = $status;
        $creditNote->setStatut(CreditNoteStatus::CANCELLED);

        // Enregistrer la raison dans les notes si fournie
        if ($reason !== null) {
            $currentNotes = $creditNote->getReason() ?? '';
            $creditNote->setReason(
                ($currentNotes ? $currentNotes . "\n\n" : '') .
                "Annulation le " . date('d/m/Y H:i') . " : " . $reason
            );
        }

        // Enregistrer l'audit
        $this->logStatusChange($creditNote, $oldStatus, CreditNoteStatus::CANCELLED, 'cancel', ['reason' => $reason]);

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Avoir annulé', [
            'credit_note_id' => $creditNote->getId(),
            'credit_note_number' => $creditNote->getNumber(),
            'old_status' => $oldStatus?->value,
            'new_status' => CreditNoteStatus::CANCELLED->value,
            'reason' => $reason,
        ]);
    }

    /**
     * Applique un avoir (ISSUED/SENT → APPLIED)
     * Signifie que l'avoir a été utilisé (remboursé ou déduit)
     */
    public function apply(CreditNote $creditNote): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('CREDIT_NOTE_APPLY', $creditNote)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission d\'appliquer cet avoir.');
        }

        $status = $creditNote->getStatut();
        if (!in_array($status, [CreditNoteStatus::ISSUED, CreditNoteStatus::SENT])) {
            throw new \RuntimeException(
                sprintf(
                    'L\'avoir ne peut pas être appliqué depuis l\'état "%s".',
                    $status?->getLabel() ?? 'inconnu'
                )
            );
        }

        $oldStatus = $status;
        $creditNote->setStatut(CreditNoteStatus::APPLIED);

        $this->logStatusChange($creditNote, $oldStatus, CreditNoteStatus::APPLIED, 'apply');
        $this->entityManager->flush();

        $this->logger->info('Avoir appliqué', [
            'credit_note_id' => $creditNote->getId(),
            'credit_note_number' => $creditNote->getNumber(),
            'old_status' => $oldStatus?->value,
            'new_status' => CreditNoteStatus::APPLIED->value,
        ]);
    }

    /**
     * Recalcule les totaux de l'avoir depuis ses lignes
     */
    public function computeTotals(CreditNote $creditNote): void
    {
        $creditNote->recalculateTotals();
        $this->entityManager->flush();
    }

    /**
     * Enregistre un changement de statut dans l'audit
     */
    private function logStatusChange(
        CreditNote $creditNote,
        ?CreditNoteStatus $oldStatus,
        CreditNoteStatus $newStatus,
        string $action,
        array $metadata = []
    ): void {
        if ($this->auditService === null) {
            return; // AuditService non injecté
        }

        $user = $this->security->getUser();
        $userId = $user instanceof User ? $user->getId() : null;

        $this->auditService->log(
            entityType: 'CreditNote',
            entityId: $creditNote->getId() ?? 0,
            action: $action,
            oldValue: ['statut' => $oldStatus?->value],
            newValue: ['statut' => $newStatus->value],
            userId: $userId,
            metadata: array_merge([
                'credit_note_number' => $creditNote->getNumber(),
                'invoice_id' => $creditNote->getInvoice()?->getId(),
                'invoice_number' => $creditNote->getInvoice()?->getNumero(),
            ], $metadata)
        );
    }
}

