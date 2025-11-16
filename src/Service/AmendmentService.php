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
     * Envoie un avenant (DRAFT/SENT → SENT)
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
        if (!in_array($status, [AmendmentStatus::DRAFT, AmendmentStatus::SENT])) {
            throw new \RuntimeException(
                sprintf(
                    'L\'avenant ne peut pas être envoyé depuis l\'état "%s".',
                    $status?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Effectuer la transition
        $oldStatus = $status;
        $amendment->setStatut(AmendmentStatus::SENT);
        $amendment->setSentAt(new \DateTime());
        $amendment->incrementSentCount();

        // Enregistrer l'audit
        $this->logStatusChange($amendment, $oldStatus, AmendmentStatus::SENT, 'send');

        // Persister
        $this->entityManager->flush();

        $this->logger->info('Avenant envoyé', [
            'amendment_id' => $amendment->getId(),
            'amendment_number' => $amendment->getNumero(),
            'old_status' => $oldStatus?->value,
            'new_status' => AmendmentStatus::SENT->value,
        ]);
    }

    /**
     * Signe un avenant (DRAFT/SENT → SIGNED)
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
        if (!in_array($status, [AmendmentStatus::DRAFT, AmendmentStatus::SENT])) {
            throw new \RuntimeException(
                sprintf(
                    'L\'avenant ne peut pas être signé depuis l\'état "%s".',
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
        if (!in_array($status, [AmendmentStatus::DRAFT, AmendmentStatus::SENT])) {
            throw new \RuntimeException(
                sprintf(
                    'L\'avenant ne peut pas être annulé depuis l\'état "%s".',
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
