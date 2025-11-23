<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Service pour l'audit et la traçabilité de toutes les actions
 * 
 * Conformité légale : Archivage 10 ans obligatoire
 * 
 * @package App\Service
 */
class AuditService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    /**
     * Enregistre une entrée d'audit
     * 
     * @param string $entityType Type d'entité (Quote, Invoice, etc.)
     * @param int $entityId ID de l'entité
     * @param string $action Action effectuée (create, update, send, sign, etc.)
     * @param array|null $oldValue Valeurs avant modification
     * @param array|null $newValue Valeurs après modification
     * @param int|null $userId ID de l'utilisateur
     * @param array|null $metadata Métadonnées supplémentaires
     * @param string|null $documentHash Hash SHA256 du document (pour PDF)
     */
    public function log(
        string $entityType,
        int $entityId,
        string $action,
        ?array $oldValue = null,
        ?array $newValue = null,
        ?int $userId = null,
        ?array $metadata = null,
        ?string $documentHash = null
    ): void {
        $auditLog = new AuditLog();
        $auditLog->setEntityType($entityType);
        $auditLog->setEntityId($entityId);
        $auditLog->setAction($action);
        $auditLog->setOldValue($oldValue);
        $auditLog->setNewValue($newValue);
        $auditLog->setMetadata($metadata);
        $auditLog->setDocumentHash($documentHash);

        // Récupérer l'utilisateur actuel si non fourni
        if ($userId === null) {
            $user = $this->security->getUser();
            if ($user instanceof User) {
                $auditLog->setUserId($user->getId());
                $auditLog->setUserEmail($user->getEmail());
            }
        } else {
            $auditLog->setUserId($userId);
            // Récupérer l'email de l'utilisateur si possible
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if ($user instanceof User) {
                $auditLog->setUserEmail($user->getEmail());
            }
        }

        $this->entityManager->persist($auditLog);
        // Note: flush() doit être appelé par le service appelant pour être dans la même transaction
    }

    /**
     * Génère un hash SHA256 d'un document (pour PDF futur)
     * 
     * @param string $content Contenu du document
     * @return string Hash SHA256
     */
    public function generateDocumentHash(string $content): string
    {
        return hash('sha256', $content);
    }
}

