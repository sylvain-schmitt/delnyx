<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReminderRuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Règle de relance automatique pour les factures impayées
 *
 * Le template d'email est défini dans templates/emails/reminder.html.twig
 * Le sujet est généré automatiquement basé sur le nom de la règle
 */
#[ORM\Entity(repositoryClass: ReminderRuleRepository::class)]
#[ORM\Table(name: 'reminder_rule')]
#[ORM\Index(columns: ['company_id', 'is_active'], name: 'idx_reminder_rule_company_active')]
#[ORM\HasLifecycleCallbacks]
class ReminderRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'Le nom de la règle est obligatoire')]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    private string $name = '';

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank(message: 'Le délai après échéance est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le délai doit être positif ou nul')]
    #[Assert\LessThanOrEqual(value: 365, message: 'Le délai ne peut pas dépasser 365 jours')]
    private int $daysAfterDue = 7;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $documentType = 'invoice';

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(type: Types::INTEGER)]
    private int $ordre = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank(message: 'Le nombre maximum de relances est obligatoire')]
    #[Assert\Positive(message: 'Le nombre max de relances doit être positif')]
    #[Assert\LessThanOrEqual(value: 10, message: 'Le nombre max de relances ne peut pas dépasser 10')]
    private int $maxReminders = 3;

    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    private ?string $companyId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'rule', targetEntity: Reminder::class)]
    private Collection $reminders;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->reminders = new ArrayCollection();
    }

    /**
     * Met à jour l'ordre automatiquement basé sur daysAfterDue avant persistance
     */
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateOrdre(): void
    {
        // L'ordre est basé sur le nombre de jours après échéance
        $this->ordre = $this->daysAfterDue;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDaysAfterDue(): int
    {
        return $this->daysAfterDue;
    }

    public function setDaysAfterDue(int $daysAfterDue): static
    {
        $this->daysAfterDue = $daysAfterDue;
        return $this;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function setDocumentType(string $documentType): static
    {
        $this->documentType = $documentType;
        return $this;
    }

    /**
     * Génère le sujet de l'email de relance
     */
    public function getEmailSubject(Invoice $invoice): string
    {
        return sprintf(
            'Rappel - Facture %s en attente de paiement',
            $invoice->getNumero() ?? 'N/A'
        );
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
        return $this;
    }

    public function getMaxReminders(): int
    {
        return $this->maxReminders;
    }

    public function setMaxReminders(int $maxReminders): static
    {
        $this->maxReminders = $maxReminders;
        return $this;
    }

    public function getCompanyId(): ?string
    {
        return $this->companyId;
    }

    public function setCompanyId(?string $companyId): static
    {
        $this->companyId = $companyId;
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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, Reminder>
     */
    public function getReminders(): Collection
    {
        return $this->reminders;
    }

    public function addReminder(Reminder $reminder): static
    {
        if (!$this->reminders->contains($reminder)) {
            $this->reminders->add($reminder);
            $reminder->setRule($this);
        }
        return $this;
    }

    public function removeReminder(Reminder $reminder): static
    {
        if ($this->reminders->removeElement($reminder)) {
            if ($reminder->getRule() === $this) {
                $reminder->setRule(null);
            }
        }
        return $this;
    }

    /**
     * Retourne un label descriptif pour l'affichage
     */
    public function getLabel(): string
    {
        if ($this->daysAfterDue === 0) {
            return sprintf('%s (le jour de l\'échéance)', $this->name);
        }
        return sprintf(
            '%s (%d jour%s après échéance)',
            $this->name,
            $this->daysAfterDue,
            $this->daysAfterDue > 1 ? 's' : ''
        );
    }
}
