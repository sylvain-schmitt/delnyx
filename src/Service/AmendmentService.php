<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Amendment;
use App\Entity\AmendmentStatus;
use App\Entity\Quote;
use App\Entity\QuoteStatus;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Bundle\SecurityBundle\Security;
use Psr\Log\LoggerInterface;

/**
 * Service pour gérer les transitions d'état et les opérations métier sur les avenants
 * 
 * Ce service centralise toute la logique métier liée aux avenants :
 * - Création depuis un devis signé
 * - Transitions d'état (send, sign, cancel)
 * - Validation des règles métier
 * - Audit et traçabilité
 * 
 * @package App\Service
 */
class AmendmentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
        private readonly AmendmentNumberGenerator $numberGenerator,
        private readonly PdfGeneratorService $pdfGeneratorService,
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
     * Crée un avenant à partir d'un devis signé
     * 
     * @throws AccessDeniedException si le devis n'est pas signé
     * @throws \RuntimeException si un avenant existe déjà
     */
    public function createFromQuote(Quote $quote): Amendment
    {
        // Vérifier que le devis est signé
        if ($quote->getStatut() !== QuoteStatus::SIGNED) {
            throw new AccessDeniedException(
                'Un avenant ne peut être créé que pour un devis signé.'
            );
        }

        // Créer l'avenant
        $amendment = new Amendment();
        $amendment->setQuote($quote);
        $amendment->setCompanyId($quote->getCompanyId());
        $amendment->setStatut(AmendmentStatus::DRAFT);
        $amendment->setTauxTVA($quote->getTauxTVA());
        // Définir des valeurs par défaut pour les champs obligatoires
        $amendment->setMotif('Modification du devis ' . $quote->getNumero());
        $amendment->setModifications('Avenant en cours de création');

        // Persister d'abord pour avoir un ID
        $this->entityManager->persist($amendment);
        $this->entityManager->flush();

        // Générer le numéro après la persistance
        if (!$amendment->getNumero()) {
            $numero = $this->numberGenerator->generate($amendment);
            $amendment->setNumero($numero);
            $this->entityManager->flush();
        }

        $this->logger->info('Avenant créé depuis devis', [
            'amendment_id' => $amendment->getId(),
            'quote_id' => $quote->getId(),
            'quote_number' => $quote->getNumero(),
        ]);

        return $amendment;
    }

    /**
     * Émet un avenant (DRAFT → ISSUED)
     * 
     * Note: Cette méthode est conservée pour backward compatibility
     * mais n'est plus utilisée dans le workflow simplifié.
     * Le workflow est maintenant : DRAFT → SENT (via send())
     * 
     * @deprecated Utiliser send() à la place
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function issue(Amendment $amendment): void
    {
        throw new \RuntimeException(
            'L\'émission d\'avenant est obsolète. Le workflow est maintenant DRAFT → SENT. Utilisez send() à la place.'
        );
    }

    /**
     * Envoie un avenant (DRAFT → SENT, ou renvoie si déjà SENT)
     * 
     * Dans le workflow simplifié :
     * - DRAFT → SENT : Génère le PDF, attribue le numéro, envoie l'email
     * - SENT → SENT : Renvoie l'email (relance)
     * 
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function send(Amendment $amendment): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('AMENDMENT_SEND', $amendment)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission d\'envoyer cet avenant.');
        }

        $status = $amendment->getStatut();
        if (!$status || !$status->canBeSent()) {
            throw new \RuntimeException(
                sprintf(
                    'L\'avenant ne peut pas être envoyé depuis l\'état "%s".',
                    $status?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Valider que l'avenant est prêt à être envoyé
        $this->validateBeforeSend($amendment);

        $oldStatus = $status;

        // Si DRAFT → SENT : Générer le PDF et attribuer le numéro
        if ($status === AmendmentStatus::DRAFT) {
            // Générer le numéro si nécessaire
            if (!$amendment->getNumero()) {
                $numero = $this->numberGenerator->generate($amendment);
                $amendment->setNumero($numero);
            }

            // Générer et sauvegarder le PDF AVANT de changer le statut
            try {
                $pdfResult = $this->pdfGeneratorService->generateAvenantPdf($amendment, true);
                $amendment->setPdfFilename($pdfResult['filename']);
                $amendment->setPdfHash($pdfResult['hash']);
            } catch (\Exception $e) {
                $this->logger->error('Erreur lors de la génération du PDF pour l\'avenant', [
                    'amendment_id' => $amendment->getId(),
                    'error' => $e->getMessage()
                ]);
            }

            // Changer le statut
            $amendment->setStatut(AmendmentStatus::SENT);
            $this->logStatusChange($amendment, $oldStatus, AmendmentStatus::SENT, 'send');
        } elseif ($status === AmendmentStatus::SENT) {
            // Si déjà SENT, juste enregistrer le renvoi
            $this->logStatusChange($amendment, $oldStatus, $oldStatus, 'resend');
        } else {
            throw new \RuntimeException(
                sprintf(
                    'L\'avenant ne peut pas être envoyé depuis l\'état "%s".',
                    $oldStatus?->getLabel() ?? 'inconnu'
                )
            );
        }
        
        // Toujours enregistrer la date d'envoi et incrémenter le compteur
        $amendment->setSentAt(new \DateTime());
        $amendment->incrementSentCount();
        
        // Par défaut, le canal est 'email' (peut être modifié plus tard)
        if (!$amendment->getDeliveryChannel()) {
            $amendment->setDeliveryChannel('email');
        }

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Avenant envoyé', [
            'amendment_id' => $amendment->getId(),
            'amendment_number' => $amendment->getNumero(),
            'old_status' => $oldStatus?->value,
            'new_status' => AmendmentStatus::SENT->value,
            'sent_count' => $amendment->getSentCount(),
        ]);
    }

    /**
     * Valide qu'un avenant peut être envoyé
     * 
     * @throws \RuntimeException si la validation échoue
     */
    private function validateBeforeSend(Amendment $amendment): void
    {
        // Vérifier qu'il y a au moins une ligne
        if ($amendment->getLines()->count() === 0) {
            throw new \RuntimeException('L\'avenant doit contenir au moins une ligne.');
        }

        // Vérifier que le devis parent existe
        if (!$amendment->getQuote()) {
            throw new \RuntimeException('L\'avenant doit être lié à un devis.');
        }

        // Vérifier que le client a un email
        $client = $amendment->getQuote()->getClient();
        if (!$client || !$client->getEmail()) {
            throw new \RuntimeException('Le client doit avoir une adresse email pour envoyer l\'avenant.');
        }
    }

    /**
     * Remet un avenant en brouillon (SENT → DRAFT)
     * 
     * Permet de modifier un avenant déjà envoyé si le client demande des ajustements.
     * Le PDF sera régénéré lors du prochain envoi.
     * 
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function backToDraft(Amendment $amendment): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('AMENDMENT_BACK_TO_DRAFT', $amendment)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission de modifier cet avenant.');
        }

        $status = $amendment->getStatut();
        if ($status !== AmendmentStatus::SENT) {
            throw new \RuntimeException(
                sprintf(
                    'L\'avenant ne peut être remis en brouillon que depuis l\'état "Envoyé". État actuel : "%s".',
                    $status?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Effectuer la transition
        $oldStatus = $status;
        $amendment->setStatut(AmendmentStatus::DRAFT);

        // Enregistrer l'audit
        $this->logStatusChange($amendment, $oldStatus, AmendmentStatus::DRAFT, 'back_to_draft', [
            'reason' => 'Retour en brouillon pour modification'
        ]);

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Avenant remis en brouillon', [
            'amendment_id' => $amendment->getId(),
            'amendment_number' => $amendment->getNumero(),
            'old_status' => $oldStatus?->value,
            'new_status' => AmendmentStatus::DRAFT->value,
        ]);
    }

    /**
     * Envoie un email de relance pour un avenant SENT
     * 
     * N'enregistre que la relance (l'envoi email est géré par le controller)
     * 
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function remind(Amendment $amendment): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('AMENDMENT_REMIND', $amendment)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission de relancer cet avenant.');
        }

        $status = $amendment->getStatut();
        if ($status !== AmendmentStatus::SENT) {
            throw new \RuntimeException(
                sprintf(
                    'L\'avenant ne peut être relancé que depuis l\'état "Envoyé". État actuel : "%s".',
                    $status?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Vérifier que le client a un email
        $client = $amendment->getQuote()?->getClient();
        if (!$client || !$client->getEmail()) {
            throw new \RuntimeException('Le client doit avoir une adresse email pour envoyer une relance.');
        }

        // Incrémenter le compteur d'envois
        $amendment->incrementSentCount();

        // Enregistrer l'audit de relance
        $this->logStatusChange($amendment, $status, $status, 'remind', [
            'sent_count' => $amendment->getSentCount(),
            'recipient' => $client->getEmail()
        ]);

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Relance envoyée pour l\'avenant', [
            'amendment_id' => $amendment->getId(),
            'amendment_number' => $amendment->getNumero(),
            'sent_count' => $amendment->getSentCount(),
            'recipient' => $client->getEmail(),
        ]);
    }

    /**
     * Signe un avenant (SENT → SIGNED)
     * 
     * @param string|null $signatureClient Signature électronique du client (optionnel)
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function sign(Amendment $amendment, ?string $signatureClient = null): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('AMENDMENT_SIGN', $amendment)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission de signer cet avenant.');
        }

        $status = $amendment->getStatut();
        if ($status !== AmendmentStatus::SENT) {
            throw new \RuntimeException(
                sprintf(
                    'L\'avenant ne peut être signé que depuis l\'état "Envoyé". État actuel : "%s".',
                    $status?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Valider que l'avenant peut être signé
        $amendment->validateCanBeSigned();

        // Effectuer la transition
        $oldStatus = $status;
        $amendment->setStatut(AmendmentStatus::SIGNED);
        $amendment->setDateSignature(new \DateTime());

        if ($signatureClient !== null) {
            $amendment->setSignatureClient($signatureClient);
        }

        // Enregistrer l'audit
        $this->logStatusChange($amendment, $oldStatus, AmendmentStatus::SIGNED, 'sign');

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Avenant signé - devient opposable', [
            'amendment_id' => $amendment->getId(),
            'amendment_number' => $amendment->getNumero(),
            'old_status' => $oldStatus?->value,
            'new_status' => AmendmentStatus::SIGNED->value,
        ]);
    }

    /**
     * Annule un avenant (DRAFT/SENT → CANCELLED)
     * 
     * @throws AccessDeniedException si l'utilisateur n'a pas la permission
     * @throws \RuntimeException si la transition n'est pas possible
     */
    public function cancel(Amendment $amendment, ?string $reason = null): void
    {
        // Vérifier les permissions
        if (!$this->authorizationChecker->isGranted('AMENDMENT_CANCEL', $amendment)) {
            throw new AccessDeniedException('Vous n\'avez pas la permission d\'annuler cet avenant.');
        }

        $status = $amendment->getStatut();
        if (!$status->canBeCancelled()) {
            throw new \RuntimeException(
                sprintf(
                    'L\'avenant ne peut être annulé que depuis les états "Brouillon" ou "Envoyé". État actuel : "%s".',
                    $status?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Effectuer la transition
        $oldStatus = $status;
        $amendment->setStatut(AmendmentStatus::CANCELLED);

        // Enregistrer la raison dans les notes si fournie
        if ($reason !== null) {
            $currentNotes = $amendment->getNotes() ?? '';
            $amendment->setNotes(
                ($currentNotes ? $currentNotes . "\n\n" : '') .
                    "Annulation le " . date('d/m/Y H:i') . " : " . $reason
            );
        }

        // Enregistrer l'audit
        $this->logStatusChange($amendment, $oldStatus, AmendmentStatus::CANCELLED, 'cancel', ['reason' => $reason]);

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Avenant annulé', [
            'amendment_id' => $amendment->getId(),
            'amendment_number' => $amendment->getNumero(),
            'old_status' => $oldStatus?->value,
            'new_status' => AmendmentStatus::CANCELLED->value,
            'reason' => $reason,
        ]);
    }

    /**
     * Recalcule les totaux de l'avenant depuis ses lignes
     */
    public function computeTotals(Amendment $amendment): void
    {
        $amendment->recalculateTotalsFromLines();
        $this->entityManager->flush();
    }

    /**
     * Enregistre un changement de statut dans l'audit
     */
    private function logStatusChange(
        Amendment $amendment,
        ?AmendmentStatus $oldStatus,
        AmendmentStatus $newStatus,
        string $action,
        array $metadata = []
    ): void {
        if ($this->auditService === null) {
            return; // AuditService non injecté
        }

        $user = $this->security->getUser();
        $userId = $user instanceof User ? $user->getId() : null;

        $this->auditService->log(
            entityType: 'Amendment',
            entityId: $amendment->getId() ?? 0,
            action: $action,
            oldValue: ['statut' => $oldStatus?->value],
            newValue: ['statut' => $newStatus->value],
            userId: $userId,
            metadata: array_merge([
                'amendment_number' => $amendment->getNumero(),
                'quote_id' => $amendment->getQuote()?->getId(),
                'quote_number' => $amendment->getQuote()?->getNumero(),
            ], $metadata)
        );
    }
}
