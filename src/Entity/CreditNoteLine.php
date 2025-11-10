<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CreditNoteLineRepository;
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

#[ORM\Entity(repositoryClass: CreditNoteLineRepository::class)]
#[ORM\Table(name: 'credit_note_lines')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['credit_note_line:read']],
    denormalizationContext: ['groups' => ['credit_note_line:write']]
)]
class CreditNoteLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['credit_note_line:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    #[Assert\Length(max: 255, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['credit_note_line:read', 'credit_note_line:write'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La quantité est obligatoire')]
    #[Assert\GreaterThan(value: 0, message: 'La quantité doit être supérieure à 0')]
    #[Groups(['credit_note_line:read', 'credit_note_line:write'])]
    private ?int $quantity = 1;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Le prix unitaire est obligatoire')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le prix unitaire ne peut pas être négatif')]
    #[Groups(['credit_note_line:read', 'credit_note_line:write'])]
    private ?int $unitPrice = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Le total HT est obligatoire')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le total HT ne peut pas être négatif')]
    #[Groups(['credit_note_line:read', 'credit_note_line:write'])]
    private ?int $totalHt = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le taux de TVA ne peut pas être négatif')]
    #[Assert\LessThanOrEqual(value: 100, message: 'Le taux de TVA ne peut pas dépasser 100%')]
    #[Groups(['credit_note_line:read', 'credit_note_line:write'])]
    private ?string $tvaRate = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'La ligne doit être liée à un avoir')]
    #[Groups(['credit_note_line:read', 'credit_note_line:write'])]
    private ?CreditNote $creditNote = null;

    #[ORM\ManyToOne]
    #[Groups(['credit_note_line:read', 'credit_note_line:write'])]
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

    public function getUnitPrice(): ?int
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(int $unitPrice): static
    {
        $this->unitPrice = $unitPrice;
        $this->recalculateTotalHt();

        return $this;
    }

    public function getTotalHt(): ?int
    {
        return $this->totalHt;
    }

    public function setTotalHt(int $totalHt): static
    {
        $this->totalHt = $totalHt;

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

    public function getCreditNote(): ?CreditNote
    {
        return $this->creditNote;
    }

    public function setCreditNote(?CreditNote $creditNote): static
    {
        $this->creditNote = $creditNote;

        // Si l'avoir est lié à une facture qui a un quote, appliquer le taux de TVA
        if ($creditNote && $creditNote->getInvoice() && $creditNote->getInvoice()->getQuote() && $creditNote->getInvoice()->getQuote()->getTauxTVA() && !$this->tvaRate) {
            $this->tvaRate = $creditNote->getInvoice()->getQuote()->getTauxTVA();
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
            // Tariff stocke déjà le prix en centimes (string décimal)
            $this->unitPrice = (int) $tariff->getPrix();
            $this->recalculateTotalHt();
        }

        return $this;
    }

    /**
     * Recalcule automatiquement le total HT à partir de la quantité et du prix unitaire
     */
    public function recalculateTotalHt(): void
    {
        if ($this->quantity !== null && $this->unitPrice !== null) {
            $this->totalHt = $this->quantity * $this->unitPrice;
        }
    }

    /**
     * Calcule le montant TTC de cette ligne
     */
    public function getTotalTtc(): int
    {
        $totalHt = $this->totalHt ?? 0;
        
        if ($this->tvaRate && (float) $this->tvaRate > 0) {
            $tvaAmount = (int) round($totalHt * ((float) $this->tvaRate / 100));
            return $totalHt + $tvaAmount;
        }

        return $totalHt;
    }

    /**
     * Retourne le total HT formaté pour l'affichage
     */
    public function getTotalHtFormatted(): string
    {
        $montant = ($this->totalHt ?? 0) / 100; // Conversion centimes -> euros
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Retourne le total TTC formaté pour l'affichage
     */
    public function getTotalTtcFormatted(): string
    {
        $montant = $this->getTotalTtc() / 100; // Conversion centimes -> euros
        return number_format($montant, 2, ',', ' ') . ' €';
    }
}

