<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité Payment - Stockage des paiements
 *
 * Gère tous les paiements liés aux factures :
 * - Paiements Stripe (carte bancaire)
 * - Paiements PayPal
 * - Paiements manuels (virement, chèque)
 */
#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payment')]
#[ORM\Index(columns: ['invoice_id'], name: 'idx_payment_invoice')]
#[ORM\Index(columns: ['status'], name: 'idx_payment_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_payment_date')]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Facture associée
     */
    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Invoice $invoice = null;

    /**
     * Montant du paiement (en centimes pour éviter les arrondis)
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank(message: 'Le montant est obligatoire')]
    #[Assert\Positive(message: 'Le montant doit être positif')]
    private ?int $amount = null;

    /**
     * Devise (ISO 4217)
     */
    #[ORM\Column(type: Types::STRING, length: 3)]
    #[Assert\NotBlank(message: 'La devise est obligatoire')]
    #[Assert\Currency(message: 'La dise n\'est pas valide')]
    private string $currency = 'EUR';

    /**
     * Provider de paiement
     */
    #[ORM\Column(type: Types::STRING, length: 20, enumType: PaymentProvider::class)]
    private PaymentProvider $provider = PaymentProvider::STRIPE;

    /**
     * ID du paiement chez le provider (Stripe Payment Intent ID, PayPal Order ID, etc.)
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $providerPaymentId = null;

    /**
     * Statut du paiement
     */
    #[ORM\Column(type: Types::STRING, length: 20, enumType: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::PENDING;

    /**
     * Métadonnées du paiement (JSON)
     * Stocke des infos spécifiques au provider :
     * - Stripe: customer_id, payment_method, receipt_url
     * - PayPal: payer_id, payer_email
     * - Manual: payment_method (virement/chèque), reference, proof_filename
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    /**
     * Date de création du paiement
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Date de validation du paiement
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    /**
     * Date de remboursement (si applicable)
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $refundedAt = null;

    /**
     * Raison de l'échec (si status = FAILED)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $failureReason = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters & Setters

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
     * Retourne le montant en euros (float)
     */
    public function getAmountInEuros(): float
    {
        return $this->amount / 100;
    }

    /**
     * Définit le montant à partir d'euros (float)
     */
    public function setAmountFromEuros(float $euros): static
    {
        $this->amount = (int) round($euros * 100);
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getProvider(): PaymentProvider
    {
        return $this->provider;
    }

    public function setProvider(PaymentProvider $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    public function getProviderPaymentId(): ?string
    {
        return $this->providerPaymentId;
    }

    public function setProviderPaymentId(?string $providerPaymentId): static
    {
        $this->providerPaymentId = $providerPaymentId;
        return $this;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): static
    {
        $this->status = $status;

        // Si le paiement est validé, définir paidAt
        if ($status === PaymentStatus::SUCCEEDED && $this->paidAt === null) {
            $this->paidAt = new \DateTimeImmutable();
        }

        // Si le paiement est remboursé, définir refundedAt
        if ($status === PaymentStatus::REFUNDED && $this->refundedAt === null) {
            $this->refundedAt = new \DateTimeImmutable();
        }

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
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

    public function getRefundedAt(): ?\DateTimeImmutable
    {
        return $this->refundedAt;
    }

    public function setRefundedAt(?\DateTimeImmutable $refundedAt): static
    {
        $this->refundedAt = $refundedAt;
        return $this;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): static
    {
        $this->failureReason = $failureReason;
        return $this;
    }

    /**
     * Retourne le montant formaté avec devise
     */
    public function getFormattedAmount(): string
    {
        return number_format($this->getAmountInEuros(), 2, ',', ' ') . ' €';
    }
}
