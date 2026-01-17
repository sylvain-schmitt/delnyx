<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReminderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Historique des relances envoyées
 */
#[ORM\Entity(repositoryClass: ReminderRepository::class)]
#[ORM\Table(name: 'reminder')]
#[ORM\Index(columns: ['invoice_id', 'rule_id'], name: 'idx_reminder_invoice_rule')]
#[ORM\Index(columns: ['sent_at'], name: 'idx_reminder_sent_at')]
class Reminder
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Invoice $invoice = null;

    #[ORM\ManyToOne(targetEntity: ReminderRule::class, inversedBy: 'reminders')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ReminderRule $rule = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $sentAt = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $emailTo = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $emailSubject = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        $this->invoice = $invoice;
        return $this;
    }

    public function getRule(): ?ReminderRule
    {
        return $this->rule;
    }

    public function setRule(?ReminderRule $rule): static
    {
        $this->rule = $rule;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getSentAt(): ?\DateTimeInterface
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeInterface $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getEmailTo(): ?string
    {
        return $this->emailTo;
    }

    public function setEmailTo(?string $emailTo): static
    {
        $this->emailTo = $emailTo;
        return $this;
    }

    public function getEmailSubject(): ?string
    {
        return $this->emailSubject;
    }

    public function setEmailSubject(?string $emailSubject): static
    {
        $this->emailSubject = $emailSubject;
        return $this;
    }

    public function markAsSent(): static
    {
        $this->status = self::STATUS_SENT;
        $this->sentAt = new \DateTime();
        return $this;
    }

    public function markAsFailed(string $errorMessage): static
    {
        $this->status = self::STATUS_FAILED;
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function markAsSkipped(string $reason): static
    {
        $this->status = self::STATUS_SKIPPED;
        $this->errorMessage = $reason;
        return $this;
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'En attente',
            self::STATUS_SENT => 'Envoyée',
            self::STATUS_FAILED => 'Échec',
            self::STATUS_SKIPPED => 'Ignorée',
            default => $this->status
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_SENT => 'green',
            self::STATUS_FAILED => 'red',
            self::STATUS_SKIPPED => 'gray',
            default => 'gray'
        };
    }
}
