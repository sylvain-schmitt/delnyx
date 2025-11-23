<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InvoiceLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;

#[ORM\Entity(repositoryClass: InvoiceLineRepository::class)]
#[ORM\Table(name: 'invoice_lines')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['invoice_line:read']],
    denormalizationContext: ['groups' => ['invoice_line:write']]
)]
class InvoiceLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['invoice_line:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    #[Assert\Length(max: 255, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['invoice_line:read', 'invoice_line:write'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La quantité est obligatoire')]
    #[Assert\GreaterThan(value: 0, message: 'La quantité doit être supérieure à 0')]
    #[Groups(['invoice_line:read', 'invoice_line:write'])]
    private ?int $quantity = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Assert\NotNull(message: 'Le prix unitaire est obligatoire')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le prix unitaire ne peut pas être négatif')]
    #[Groups(['invoice_line:read', 'invoice_line:write'])]
    private string $unitPrice = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Assert\NotNull(message: 'Le total HT est obligatoire')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le total HT ne peut pas être négatif')]
    #[Groups(['invoice_line:read', 'invoice_line:write'])]
    private string $totalHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le taux de TVA ne peut pas être négatif')]
    #[Assert\LessThanOrEqual(value: 100, message: 'Le taux de TVA ne peut pas dépasser 100%')]
    #[Groups(['invoice_line:read', 'invoice_line:write'])]
    private ?string $tvaRate = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'La ligne doit être liée à une facture')]
    #[Groups(['invoice_line:read', 'invoice_line:write'])]
    private ?Invoice $invoice = null;

    #[ORM\ManyToOne]
    #[Groups(['invoice_line:read', 'invoice_line:write'])]
    private ?Tariff $tariff = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        $this->recalculateTotalHt();

        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?string $unitPrice): static
    {
        $this->unitPrice = $unitPrice ?? '0.00';
        $this->recalculateTotalHt();

        return $this;
    }

    public function getTotalHt(): ?string
    {
        return $this->totalHt;
    }

    public function setTotalHt(?string $totalHt): static
    {
        $this->totalHt = $totalHt ?? '0.00';

        return $this;
    }

    public function getTvaRate(): ?string
    {
        return $this->tvaRate;
    }

    public function setTvaRate(?string $tvaRate): static
    {
        $this->tvaRate = $tvaRate;

        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        $this->invoice = $invoice;

        // Si l'invoice a un taux de TVA (via le quote), l'appliquer à la ligne si non défini
        if ($invoice && $invoice->getQuote() && $invoice->getQuote()->getTauxTVA() && !$this->tvaRate) {
            $this->tvaRate = $invoice->getQuote()->getTauxTVA();
        }

        return $this;
    }

    public function getTariff(): ?Tariff
    {
        return $this->tariff;
    }

    public function setTariff(?Tariff $tariff): static
    {
        $this->tariff = $tariff;

        // Si un tarif est associé, remplir automatiquement les informations
        if ($tariff) {
            $this->description = $tariff->getTitre();
            // Tariff stocke le prix en euros (DECIMAL), utiliser directement
            $prixEnEuros = (float) $tariff->getPrix();
            $this->unitPrice = number_format($prixEnEuros, 2, '.', '');
            $this->recalculateTotalHt();
        }

        return $this;
    }

    /**
     * Recalcule automatiquement le total HT à partir de la quantité et du prix unitaire
     * Les montants sont stockés en euros (DECIMAL)
     */
    public function recalculateTotalHt(): void
    {
        if ($this->quantity !== null && $this->unitPrice !== null) {
            $total = (float) $this->unitPrice * $this->quantity;
            $this->totalHt = number_format($total, 2, '.', '');
        }
    }

    /**
     * Calcule le montant TTC de cette ligne
     * Retourne en euros (string)
     */
    public function getTotalTtc(): string
    {
        $totalHt = (float) ($this->totalHt ?? 0);
        
        if ($this->tvaRate && (float) $this->tvaRate > 0) {
            $tvaAmount = $totalHt * ((float) $this->tvaRate / 100);
            return number_format($totalHt + $tvaAmount, 2, '.', '');
        }

        return number_format($totalHt, 2, '.', '');
    }

    /**
     * Retourne le total HT formaté pour l'affichage
     * Les montants sont déjà en euros (DECIMAL)
     */
    public function getTotalHtFormatted(): string
    {
        $montant = (float) ($this->totalHt ?? 0);
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Retourne le total TTC formaté pour l'affichage
     * Les montants sont déjà en euros (DECIMAL)
     */
    public function getTotalTtcFormatted(): string
    {
        $montant = (float) $this->getTotalTtc();
        return number_format($montant, 2, ',', ' ') . ' €';
    }
}

