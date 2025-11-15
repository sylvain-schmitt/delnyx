<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

/**
 * Entité pour l'audit et la traçabilité de toutes les actions sur les documents
 * 
 * Conformité légale : Archivage 10 ans obligatoire pour les documents contractuels
 * 
 * @package App\Entity
 */
#[ORM\Entity]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_entity')]
#[ORM\Index(columns: ['user_id'], name: 'idx_user')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created_at')]
#[ORM\HasLifecycleCallbacks]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /**
     * Type d'entité (Quote, Invoice, Amendment, CreditNote)
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $entityType;

    /**
     * ID de l'entité concernée
     */
    #[ORM\Column(type: Types::INTEGER)]
    private int $entityId;

    /**
     * Action effectuée (create, update, delete, send, sign, cancel, etc.)
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $action;

    /**
     * Valeurs avant modification (JSON)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $oldValue = null;

    /**
     * Valeurs après modification (JSON)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $newValue = null;

    /**
     * Métadonnées supplémentaires (JSON)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    /**
     * ID de l'utilisateur qui a effectué l'action
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $userId = null;

    /**
     * Email de l'utilisateur (pour traçabilité même si l'utilisateur est supprimé)
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $userEmail = null;

    /**
     * Hash SHA256 du document (pour PDF futur)
     */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $documentHash = null;

    /**
     * Date de création de l'entrée d'audit
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): static
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getOldValue(): ?array
    {
        return $this->oldValue;
    }

    public function setOldValue(?array $oldValue): static
    {
        $this->oldValue = $oldValue;
        return $this;
    }

    public function getNewValue(): ?array
    {
        return $this->newValue;
    }

    public function setNewValue(?array $newValue): static
    {
        $this->newValue = $newValue;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): static
    {
        $this->userId = $userId;
        return $this;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function setUserEmail(?string $userEmail): static
    {
        $this->userEmail = $userEmail;
        return $this;
    }

    public function getDocumentHash(): ?string
    {
        return $this->documentHash;
    }

    public function setDocumentHash(?string $documentHash): static
    {
        $this->documentHash = $documentHash;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
    }
}

