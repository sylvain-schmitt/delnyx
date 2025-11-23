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
        new Post(
            security: "is_granted('ROLE_USER') && object.getCreditNote() !== null && object.getCreditNote().getStatutEnum() !== null && object.getCreditNote().getStatutEnum().isModifiable()",
            securityMessage: "Seuls les utilisateurs authentifiés peuvent créer des lignes d'avoir via l'API. L'avoir doit être modifiable (brouillon)."
        ),
        new Put(
            security: "is_granted('ROLE_USER') && object.getCreditNote() !== null && object.getCreditNote().getStatutEnum() !== null && object.getCreditNote().getStatutEnum().isModifiable()",
            securityMessage: "Seules les lignes d'avoir en brouillon peuvent être modifiées via l'API."
        ),
        new Delete(
            security: "is_granted('ROLE_USER') && object.getCreditNote() !== null && object.getCreditNote().getStatutEnum() !== null && object.getCreditNote().getStatutEnum().isModifiable()",
            securityMessage: "Seules les lignes d'avoir en brouillon peuvent être supprimées via l'API."
        )
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

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Assert\NotNull(message: 'Le prix unitaire est obligatoire')]
    #[Groups(['credit_note_line:read', 'credit_note_line:write'])]
    private string $unitPrice = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Assert\NotNull(message: 'Le total HT est obligatoire')]
    #[Groups(['credit_note_line:read', 'credit_note_line:write'])]
    private string $totalHt = '0.00';

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

    // ===== CHAMPS POUR LE PRINCIPE DU DELTA (CONFORMITÉ LÉGALE) =====
    /**
     * Référence à la ligne de la facture d'origine (si cette ligne modifie une ligne existante)
     * NULL si c'est une ligne ajoutée (pas de modification d'une ligne existante)
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['credit_note_line:read', 'credit_note_line:write'])]
    private ?InvoiceLine $sourceLine = null;

    /**
     * Valeur d'origine (en euros, DECIMAL)
     * Pour une ligne modifiée : total HT de la ligne source (positif)
     * Pour une ligne ajoutée : 0.00
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Groups(['credit_note_line:read'])]
    private string $oldValue = '0.00';

    /**
     * Nouvelle valeur (en euros, DECIMAL)
     * Pour une ligne modifiée : nouveau total HT calculé (peut être négatif pour un avoir)
     * Pour une ligne ajoutée : total HT de la nouvelle ligne (négatif pour un avoir)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Groups(['credit_note_line:read'])]
    private string $newValue = '0.00';

    /**
     * Delta = newValue - oldValue (en euros, DECIMAL)
     * Pour un avoir, le delta est généralement négatif (crédit)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Groups(['credit_note_line:read'])]
    private string $delta = '0.00';

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

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?string $unitPrice): static
    {
        $this->unitPrice = $unitPrice ?? '0.00';
        $this->recalculateTotalHt();

        return $this;
    }

    public function getTotalHt(): string
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
            $this->description = $tariff->getNom();
            // Tariff stocke le prix en euros (DECIMAL)
            $this->unitPrice = $tariff->getPrix();
            $this->recalculateTotalHt();
        }

        return $this;
    }

    /**
     * Recalcule automatiquement le total HT à partir de la quantité et du prix unitaire
     * Pour les avoirs, le total doit être négatif (crédit)
     * Les montants sont stockés en DECIMAL (euros)
     * Met aussi à jour newValue et recalcule le delta
     * 
     * LOGIQUE :
     * - Si sourceLine est défini : unitPrice représente le DELTA (ajustement, généralement négatif)
     *   → oldValue = sourceLine.totalHt
     *   → newValue = oldValue + (unitPrice × quantity)
     * - Si sourceLine est NULL : unitPrice représente la nouvelle valeur totale (généralement négative)
     *   → oldValue = 0.00
     *   → newValue = unitPrice × quantity (déjà négatif pour un avoir)
     */
    public function recalculateTotalHt(): void
    {
        if ($this->quantity !== null && $this->unitPrice !== null) {
            // Définir oldValue en premier si sourceLine est défini
            if ($this->sourceLine && !$this->oldValue) {
                $oldValue = (float) $this->sourceLine->getTotalHt();
                $this->oldValue = number_format($oldValue, 2, '.', '');
            } elseif (!$this->sourceLine && !$this->oldValue) {
                $this->oldValue = '0.00';
            }
            
            if ($this->sourceLine) {
                // MODIFICATION : unitPrice représente le DELTA (ajustement, généralement négatif)
                // totalHt = delta
                $oldValue = (float) $this->oldValue;
                $delta = (float) $this->unitPrice * $this->quantity;
                $newValue = $oldValue + $delta;
                
                // FIX: totalHt stocke le delta (montant de l'avoir pour cette ligne)
                $this->totalHt = number_format($delta, 2, '.', '');
                $this->newValue = number_format($newValue, 2, '.', '');
            } else {
                // AJOUT : unitPrice représente la nouvelle valeur totale (généralement négative pour un avoir)
                $total = (float) $this->unitPrice * $this->quantity;
                // Pour un avoir, le montant doit être négatif (crédit)
                // Si le prix unitaire est positif, on le rend négatif
                if ($total > 0) {
                    $total = -$total;
                }
                $this->totalHt = number_format($total, 2, '.', '');
                $this->newValue = $this->totalHt;
            }
            // Recalculer le delta
            $this->recalculateDelta();
        }
    }

    /**
     * Calcule le montant TTC de cette ligne
     * Pour les avoirs, les montants sont négatifs (crédit)
     * Les montants sont stockés en DECIMAL (euros)
     */
    public function getTotalTtc(): string
    {
        $totalHt = (float) $this->totalHt;
        
        if ($this->tvaRate && (float) $this->tvaRate > 0) {
            // La TVA est également négative pour un avoir
            $tvaAmount = abs($totalHt) * ((float) $this->tvaRate / 100);
            // Conserver le signe négatif
            $tvaAmount = -$tvaAmount;
            return number_format($totalHt + $tvaAmount, 2, '.', '');
        }

        return $this->totalHt;
    }

    /**
     * Retourne le total HT formaté pour l'affichage
     */
    public function getTotalHtFormatted(): string
    {
        $montant = (float) $this->totalHt; // Déjà en euros (DECIMAL)
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Retourne le total TTC formaté pour l'affichage
     */
    public function getTotalTtcFormatted(): string
    {
        $montant = (float) $this->getTotalTtc(); // Déjà en euros (DECIMAL)
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    // ===== GETTERS/SETTERS POUR LE PRINCIPE DU DELTA =====

    public function getSourceLine(): ?InvoiceLine
    {
        return $this->sourceLine;
    }

    public function setSourceLine(?InvoiceLine $sourceLine): static
    {
        $this->sourceLine = $sourceLine;
        return $this;
    }

    public function getOldValue(): string
    {
        return $this->oldValue;
    }

    public function setOldValue(string $oldValue): static
    {
        $this->oldValue = $oldValue;
        $this->recalculateDelta();
        return $this;
    }

    public function getNewValue(): string
    {
        return $this->newValue;
    }

    public function setNewValue(string $newValue): static
    {
        $this->newValue = $newValue;
        $this->recalculateDelta();
        return $this;
    }

    public function getDelta(): string
    {
        return $this->delta;
    }

    public function setDelta(string $delta): static
    {
        $this->delta = $delta;
        return $this;
    }

    /**
     * Recalcule automatiquement le delta = newValue - oldValue
     */
    public function recalculateDelta(): void
    {
        $old = (float) $this->oldValue;
        $new = (float) $this->newValue;
        $delta = $new - $old;
        $this->delta = number_format($delta, 2, '.', '');
    }

    /**
     * Retourne le delta formaté pour l'affichage
     */
    public function getDeltaFormatted(): string
    {
        $delta = (float) $this->delta;
        $sign = $delta >= 0 ? '+' : '';
        return $sign . number_format($delta, 2, ',', ' ') . ' €';
    }

    /**
     * Indique si cette ligne modifie une ligne existante ou en ajoute une nouvelle
     */
    public function isModification(): bool
    {
        return $this->sourceLine !== null;
    }

    /**
     * Indique si cette ligne est une nouvelle ligne (pas de modification)
     */
    public function isAddition(): bool
    {
        return $this->sourceLine === null;
    }
}
