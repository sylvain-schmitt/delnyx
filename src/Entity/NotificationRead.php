<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationReadRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité pour tracker les notifications lues par utilisateur
 */
#[ORM\Entity(repositoryClass: NotificationReadRepository::class)]
#[ORM\Table(name: 'notification_reads')]
#[ORM\UniqueConstraint(name: 'unique_user_notification', columns: ['user_id', 'notification_key'])]
#[ORM\Index(name: 'idx_notification_user', columns: ['user_id'])]
class NotificationRead
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * Clé unique de la notification (ex: "appointment_123", "invoice_456")
     */
    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $notificationKey = '';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $readAt;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isHidden = false;

    public function __construct()
    {
        $this->readAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getNotificationKey(): string
    {
        return $this->notificationKey;
    }

    public function setNotificationKey(string $notificationKey): self
    {
        $this->notificationKey = $notificationKey;
        return $this;
    }

    public function getReadAt(): \DateTimeInterface
    {
        return $this->readAt;
    }

    public function setReadAt(\DateTimeInterface $readAt): self
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function isHidden(): bool
    {
        return $this->isHidden;
    }

    public function setIsHidden(bool $isHidden): self
    {
        $this->isHidden = $isHidden;
        return $this;
    }
}
