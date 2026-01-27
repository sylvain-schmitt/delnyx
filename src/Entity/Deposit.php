<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DepositRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité Deposit - Accomptes sur devis
 *
 * Gère les paiements d'accompte demandés après signature d'un devis.
 * L'accompte est ensuite déduit de la facture finale.
 */
#[ORM\Entity(repositoryClass: DepositRepository::class)]
#[ORM\Table(name: 'deposit')]
#[ORM\Index(columns: ['quote_id'], name: 'idx_deposit_quote')]
#[ORM\Index(columns: ['status'], name: 'idx_deposit_status')]
#[ORM\Index(columns: ['invoice_id'], name: 'idx_deposit_invoice')]
class Deposit
{
    public const DEFAULT_PERCENTAGE = 30.0;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Devis associé
     */
    #[ORM\ManyToOne(targetEntity: Quote::class, inversedBy: 'deposits')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Quote $quote = null;

    /**
     * Montant de l'accompte (en centimes)
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank(message: 'Le montant est obligatoire')]
    #[Assert\Positive(message: 'Le montant doit être positif')]
    private ?int $amount = null;

    /**
     * Pourcentage du devis (ex: 30.0 pour 30%)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $percentage = null;

    /**
     * Statut de l'accompte
     */
    #[ORM\Column(type: Types::STRING, length: 20, enumType: DepositStatus::class)]
    private DepositStatus $status = DepositStatus::PENDING;

    /**
     * Date de demande de l'accompte
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $requestedAt = null;

    /**
     * Date de paiement
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    /**
     * ID de session Stripe Checkout
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $stripeSessionId = null;

    /**
     * ID du PaymentIntent Stripe
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    /**
     * Facture sur laquelle l'accompte a été déduit
     */
    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'deposits')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Invoice $invoice = null;

    /**
     * Date de déduction sur facture
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deductedAt = null;

    /**
     * Notes internes
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * Facture d'acompte générée pour ce paiement
     * (relation inverse de Invoice.sourceDeposit)
     */
    #[ORM\OneToOne(targetEntity: Invoice::class, mappedBy: 'sourceDeposit')]
    private ?Invoice $depositInvoice = null;

    public function __construct()
    {
        $this->requestedAt = new \DateTimeImmutable();
    }

    // ==================== Getters & Setters ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuote(): ?Quote
    {
        return $this->quote;
    }

    public function setQuote(?Quote $quote): static
    {
        $this->quote = $quote;
        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * Montant en euros
     */
    public function getAmountInEuros(): float
    {
        return ($this->amount ?? 0) / 100;
    }

    /**
     * Définit le montant depuis des euros
     */
    public function setAmountFromEuros(float $euros): static
    {
        $this->amount = (int) round($euros * 100);
        return $this;
    }

    /**
     * Montant formaté
     */
    public function getFormattedAmount(): string
    {
        return number_format($this->getAmountInEuros(), 2, ',', ' ') . ' €';
    }

    public function getPercentage(): ?float
    {
        return $this->percentage !== null ? (float) $this->percentage : null;
    }

    public function setPercentage(?float $percentage): static
    {
        $this->percentage = $percentage !== null ? (string) $percentage : null;
        return $this;
    }

    public function getStatus(): DepositStatus
    {
        return $this->status;
    }

    public function setStatus(DepositStatus $status): static
    {
        $this->status = $status;

        if ($status === DepositStatus::PAID && $this->paidAt === null) {
            $this->paidAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getRequestedAt(): ?\DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(\DateTimeImmutable $requestedAt): static
    {
        $this->requestedAt = $requestedAt;
        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;
        return $this;
    }

    public function getStripeSessionId(): ?string
    {
        return $this->stripeSessionId;
    }

    public function setStripeSessionId(?string $stripeSessionId): static
    {
        $this->stripeSessionId = $stripeSessionId;
        return $this;
    }

    public function getStripePaymentIntentId(): ?string
    {
        return $this->stripePaymentIntentId;
    }

    public function setStripePaymentIntentId(?string $stripePaymentIntentId): static
    {
        $this->stripePaymentIntentId = $stripePaymentIntentId;
        return $this;
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

    public function getDeductedAt(): ?\DateTimeImmutable
    {
        return $this->deductedAt;
    }

    public function setDeductedAt(?\DateTimeImmutable $deductedAt): static
    {
        $this->deductedAt = $deductedAt;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    // ==================== Méthodes utilitaires ====================

    /**
     * Vérifie si l'accompte est payé
     */
    public function isPaid(): bool
    {
        return $this->status === DepositStatus::PAID;
    }

    /**
     * Vérifie si l'accompte a été déduit d'une facture
     */
    public function isDeducted(): bool
    {
        return $this->invoice !== null && $this->deductedAt !== null;
    }

    /**
     * Vérifie si l'accompte peut être remboursé
     */
    public function canBeRefunded(): bool
    {
        return $this->status->canBeRefunded() && $this->stripePaymentIntentId !== null;
    }

    /**
     * Marque l'accompte comme déduit sur une facture
     */
    public function markAsDeducted(Invoice $invoice): static
    {
        $this->invoice = $invoice;
        $this->deductedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Retourne un libellé pour l'affichage
     */
    public function getLabel(): string
    {
        $label = sprintf('Accompte de %s', $this->getFormattedAmount());

        if ($this->percentage !== null) {
            $label .= sprintf(' (%s%%)', number_format((float) $this->percentage, 0));
        }

        return $label;
    }

    /**
     * Retourne la facture d'acompte générée pour ce paiement
     */
    public function getDepositInvoice(): ?Invoice
    {
        return $this->depositInvoice;
    }

    /**
     * Définit la facture d'acompte
     */
    public function setDepositInvoice(?Invoice $invoice): self
    {
        $this->depositInvoice = $invoice;
        return $this;
    }

    /**
     * Vérifie si une facture d'acompte a été générée
     */
    public function hasDepositInvoice(): bool
    {
        return $this->depositInvoice !== null;
    }
}
