<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PurchaseInvoiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PurchaseInvoiceRepository::class)]
#[ORM\Table(name: 'purchase_invoices')]
#[ORM\HasLifecycleCallbacks]
class PurchaseInvoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 36)]
    private string $companyId;

    /** ID de la facture sur Super PDP (direction=in) */
    #[ORM\Column(type: Types::INTEGER)]
    private int $pdpInvoiceId;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sellerName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $sellerSiren = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $buyerName = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $totalHT = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $totalTTC = null;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $issueDate = null;

    /** fr:200, fr:201, fr:210, fr:211, fr:212, fr:220 … */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $pdpStatus = null;

    /** Réponse brute JSON de Super PDP */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $pdpRawResponse = null;

    /** 'acknowledged' | 'rejected' | null */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $localStatus = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $acknowledgedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $rejectedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $invoiceNumber = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lines = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $vatBreakdown = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $localPdfPath = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getCompanyId(): string { return $this->companyId; }
    public function setCompanyId(string $companyId): static { $this->companyId = $companyId; return $this; }

    public function getPdpInvoiceId(): int { return $this->pdpInvoiceId; }
    public function setPdpInvoiceId(int $pdpInvoiceId): static { $this->pdpInvoiceId = $pdpInvoiceId; return $this; }

    public function getExternalId(): ?string { return $this->externalId; }
    public function setExternalId(?string $externalId): static { $this->externalId = $externalId; return $this; }

    public function getSellerName(): ?string { return $this->sellerName; }
    public function setSellerName(?string $sellerName): static { $this->sellerName = $sellerName; return $this; }

    public function getSellerSiren(): ?string { return $this->sellerSiren; }
    public function setSellerSiren(?string $sellerSiren): static { $this->sellerSiren = $sellerSiren; return $this; }

    public function getBuyerName(): ?string { return $this->buyerName; }
    public function setBuyerName(?string $buyerName): static { $this->buyerName = $buyerName; return $this; }

    public function getTotalHT(): ?string { return $this->totalHT; }
    public function setTotalHT(?string $totalHT): static { $this->totalHT = $totalHT; return $this; }

    public function getTotalTTC(): ?string { return $this->totalTTC; }
    public function setTotalTTC(?string $totalTTC): static { $this->totalTTC = $totalTTC; return $this; }

    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): static { $this->currency = $currency; return $this; }

    public function getIssueDate(): ?\DateTimeInterface { return $this->issueDate; }
    public function setIssueDate(?\DateTimeInterface $issueDate): static { $this->issueDate = $issueDate; return $this; }

    public function getPdpStatus(): ?string { return $this->pdpStatus; }
    public function setPdpStatus(?string $pdpStatus): static { $this->pdpStatus = $pdpStatus; return $this; }

    public function getPdpRawResponse(): ?string { return $this->pdpRawResponse; }
    public function setPdpRawResponse(?string $pdpRawResponse): static { $this->pdpRawResponse = $pdpRawResponse; return $this; }

    public function getLocalStatus(): ?string { return $this->localStatus; }
    public function setLocalStatus(?string $localStatus): static { $this->localStatus = $localStatus; return $this; }

    public function getAcknowledgedAt(): ?\DateTimeInterface { return $this->acknowledgedAt; }
    public function setAcknowledgedAt(?\DateTimeInterface $acknowledgedAt): static { $this->acknowledgedAt = $acknowledgedAt; return $this; }

    public function getRejectedAt(): ?\DateTimeInterface { return $this->rejectedAt; }
    public function setRejectedAt(?\DateTimeInterface $rejectedAt): static { $this->rejectedAt = $rejectedAt; return $this; }

    public function getRejectionReason(): ?string { return $this->rejectionReason; }
    public function setRejectionReason(?string $rejectionReason): static { $this->rejectionReason = $rejectionReason; return $this; }

    public function getInvoiceNumber(): ?string { return $this->invoiceNumber; }
    public function setInvoiceNumber(?string $invoiceNumber): static { $this->invoiceNumber = $invoiceNumber; return $this; }

    public function getLines(): array { return json_decode($this->lines ?? '[]', true) ?: []; }
    public function setLines(array $lines): static { $this->lines = json_encode($lines, JSON_UNESCAPED_UNICODE); return $this; }

    public function getVatBreakdown(): array { return json_decode($this->vatBreakdown ?? '[]', true) ?: []; }
    public function setVatBreakdown(array $vatBreakdown): static { $this->vatBreakdown = json_encode($vatBreakdown, JSON_UNESCAPED_UNICODE); return $this; }

    public function getLocalPdfPath(): ?string { return $this->localPdfPath; }
    public function setLocalPdfPath(?string $localPdfPath): static { $this->localPdfPath = $localPdfPath; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    public function getPdpStatusLabel(): string
    {
        return match ($this->pdpStatus) {
            'fr:200' => 'Déposée',
            'fr:201' => 'Acceptée PA',
            'fr:210' => 'Rejetée PA',
            'fr:211' => 'Rejetée destinataire',
            'fr:212' => 'Encaissée',
            'fr:220' => 'Annulée',
            default  => $this->pdpStatus ?? 'Inconnue',
        };
    }

    public function getPdpStatusColor(): string
    {
        return match ($this->pdpStatus) {
            'fr:200' => 'blue',
            'fr:201' => 'green',
            'fr:210', 'fr:211' => 'red',
            'fr:212' => 'emerald',
            'fr:220' => 'gray',
            default  => 'gray',
        };
    }
}
