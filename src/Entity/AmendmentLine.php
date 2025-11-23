<?php

declare(strict_types=1);

namespace App\Entity;

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

#[ORM\Entity(repositoryClass: \App\Repository\AmendmentLineRepository::class)]
#[ORM\Table(name: 'amendment_lines')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            security: "is_granted('ROLE_USER') && object.getAmendment() !== null && object.getAmendment().getStatutEnum() !== null && object.getAmendment().getStatutEnum().isModifiable()",
            securityMessage: "Seuls les utilisateurs authentifiés peuvent créer des lignes d'avenant via l'API. L'avenant doit être modifiable (brouillon)."
        ),
        new Put(
            security: "is_granted('ROLE_USER') && object.getAmendment() !== null && object.getAmendment().getStatutEnum() !== null && object.getAmendment().getStatutEnum().isModifiable()",
            securityMessage: "Seules les lignes d'avenant en brouillon peuvent être modifiées via l'API."
        ),
        new Delete(
            security: "is_granted('ROLE_USER') && object.getAmendment() !== null && object.getAmendment().getStatutEnum() !== null && object.getAmendment().getStatutEnum().isModifiable()",
            securityMessage: "Seules les lignes d'avenant en brouillon peuvent être supprimées via l'API."
        )
    ],
    normalizationContext: ['groups' => ['amendment_line:read']],
    denormalizationContext: ['groups' => ['amendment_line:write']]
)]
class AmendmentLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['amendment_line:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    #[Assert\Length(max: 255, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['amendment_line:read', 'amendment_line:write'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La quantité est obligatoire')]
    #[Assert\GreaterThan(value: 0, message: 'La quantité doit être supérieure à 0')]
    #[Groups(['amendment_line:read', 'amendment_line:write'])]
    private ?int $quantity = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Assert\NotNull(message: 'Le prix unitaire est obligatoire')]
    #[Groups(['amendment_line:read', 'amendment_line:write'])]
    private string $unitPrice = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Assert\NotNull(message: 'Le total HT est obligatoire')]
    #[Groups(['amendment_line:read', 'amendment_line:write'])]
    private string $totalHt = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le taux de TVA ne peut pas être négatif')]
    #[Assert\LessThanOrEqual(value: 100, message: 'Le taux de TVA ne peut pas dépasser 100%')]
    #[Groups(['amendment_line:read', 'amendment_line:write'])]
    private ?string $tvaRate = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'La ligne doit être liée à un avenant')]
    #[Groups(['amendment_line:read', 'amendment_line:write'])]
    private ?Amendment $amendment = null;

    #[ORM\ManyToOne]
    #[Groups(['amendment_line:read', 'amendment_line:write'])]
    private ?Tariff $tariff = null;

    // ===== CHAMPS POUR LE PRINCIPE DU DELTA (CONFORMITÉ LÉGALE) =====
    /**
     * Référence à la ligne du devis d'origine (si cette ligne modifie une ligne existante)
     * NULL si c'est une ligne ajoutée (pas de modification d'une ligne existante)
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['amendment_line:read', 'amendment_line:write'])]
    private ?QuoteLine $sourceLine = null;

    /**
     * Valeur d'origine (en euros, DECIMAL)
     * Pour une ligne modifiée : total HT de la ligne source
     * Pour une ligne ajoutée : 0.00
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Groups(['amendment_line:read'])]
    private string $oldValue = '0.00';

    /**
     * Nouvelle valeur (en euros, DECIMAL)
     * Pour une ligne modifiée : nouveau total HT calculé
     * Pour une ligne ajoutée : total HT de la nouvelle ligne
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Groups(['amendment_line:read'])]
    private string $newValue = '0.00';

    /**
     * Delta = newValue - oldValue (en euros, DECIMAL)
     * Peut être positif (augmentation) ou négatif (diminution)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Groups(['amendment_line:read'])]
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

    public function getAmendment(): ?Amendment
    {
        return $this->amendment;
    }

    public function setAmendment(?Amendment $amendment): static
    {
        $this->amendment = $amendment;
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
            // Tariff stocke le prix en euros (DECIMAL)
            $this->unitPrice = $tariff->getPrix();
            $this->recalculateTotalHt();
        }

        return $this;
    }

    /**
     * Recalcule automatiquement le total HT à partir de la quantité et du prix unitaire
     * Les montants sont stockés en DECIMAL (euros)
     * Met aussi à jour newValue et recalcule le delta
     * 
     * LOGIQUE :
     * - Si sourceLine est défini : unitPrice représente le DELTA (ajustement)
     *   → oldValue = sourceLine.totalHt
     *   → newValue = oldValue + (unitPrice × quantity)
     * - Si sourceLine est NULL : unitPrice représente la nouvelle valeur totale
     *   → oldValue = 0.00
     *   → newValue = unitPrice × quantity
     */
    public function recalculateTotalHt(): void
    {
        if ($this->quantity !== null && $this->unitPrice !== null) {
            if ($this->sourceLine) {
                // MODIFICATION : unitPrice représente le DELTA (ajustement)
                // Définir oldValue en premier si pas déjà défini
                if (!$this->oldValue || $this->oldValue === '0.00') {
                    $oldValue = (float) $this->sourceLine->getTotalHt();
                    $this->oldValue = number_format($oldValue, 2, '.', '');
                }
                
                // newValue = oldValue + delta
                $oldValue = (float) $this->oldValue;
                $delta = (float) $this->unitPrice * $this->quantity;
                $newValue = $oldValue + $delta;
                $this->totalHt = number_format($newValue, 2, '.', '');
                $this->newValue = $this->totalHt;
            } else {
                // AJOUT : unitPrice représente la nouvelle valeur totale
                if (!$this->oldValue || $this->oldValue === '0.00') {
                    $this->oldValue = '0.00';
                }
                
                $total = (float) $this->unitPrice * $this->quantity;
                $this->totalHt = number_format($total, 2, '.', '');
                $this->newValue = $this->totalHt;
            }
            // Recalculer le delta
            $this->recalculateDelta();
        }
    }

    /**
     * Calcule le montant TTC de cette ligne
     * Les montants sont stockés en DECIMAL (euros)
     * Utilise la même logique que getDeltaTtc() pour déterminer le taux de TVA
     */
    public function getTotalTtc(): string
    {
        $totalHt = (float) $this->totalHt;

        // Déterminer le taux de TVA à utiliser (même logique que getDeltaTtc())
        $tvaRate = null;
        
        if ($this->sourceLine) {
            // Pour une modification, utiliser le taux de TVA de la ligne source
            $quote = $this->sourceLine->getQuote();
            if ($quote) {
                if ($quote->isUsePerLineTva()) {
                    // TVA par ligne : utiliser le taux de la ligne source
                    $sourceTvaRate = $this->sourceLine->getTvaRate();
                    $tvaRate = ($sourceTvaRate && (float) $sourceTvaRate > 0) ? (float) $sourceTvaRate : null;
                } else {
                    // TVA globale : utiliser le taux global du devis
                    $quoteTvaRate = $quote->getTauxTVA();
                    $tvaRate = ($quoteTvaRate && (float) $quoteTvaRate > 0) ? (float) $quoteTvaRate : null;
                }
            }
        }
        
        // Si pas de taux depuis la source (ligne ajoutée), utiliser celui de la ligne d'avenant ou de l'avenant
        if ($tvaRate === null) {
            if ($this->tvaRate && (float) $this->tvaRate > 0) {
                $tvaRate = (float) $this->tvaRate;
            } elseif ($this->amendment && $this->amendment->getTauxTVA()) {
                $tvaRate = (float) $this->amendment->getTauxTVA();
            }
        }
        
        // Si toujours pas de taux, utiliser le taux global du devis
        if ($tvaRate === null && $this->amendment && $this->amendment->getQuote()) {
            $quote = $this->amendment->getQuote();
            $quoteTvaRate = $quote->getTauxTVA();
            if ($quoteTvaRate && (float) $quoteTvaRate > 0) {
                $tvaRate = (float) $quoteTvaRate;
            }
        }

        // Appliquer la TVA au total HT pour obtenir le total TTC
        if ($tvaRate !== null && $tvaRate > 0) {
            $tvaAmount = $totalHt * ($tvaRate / 100);
            return number_format($totalHt + $tvaAmount, 2, '.', '');
        }

        // Pas de TVA : total TTC = total HT
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

    public function getSourceLine(): ?QuoteLine
    {
        return $this->sourceLine;
    }

    public function setSourceLine(?QuoteLine $sourceLine): static
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
     * Calcule le delta TTC à partir du delta HT
     * IMPORTANT : Le delta TTC doit être calculé en appliquant la TVA directement au delta HT
     * et non pas comme la différence entre newValueTTC et oldValueTTC, car cela créerait une double déduction
     * 
     * Le delta HT est déjà la différence entre newValue et oldValue,
     * donc on applique simplement la TVA au delta HT pour obtenir le delta TTC
     */
    public function getDeltaTtc(): string
    {
        // Le delta HT est déjà calculé (newValue - oldValue)
        $deltaHt = (float) $this->delta;
        
        // Récupérer le taux de TVA utilisé pour calculer le montant TTC du devis original
        // C'est le taux de la ligne source si usePerLineTva, sinon le taux global du devis
        $tvaRate = null;
        if ($this->sourceLine) {
            // Pour une modification, utiliser le taux de TVA de la ligne source
            $quote = $this->sourceLine->getQuote();
            if ($quote) {
                if ($quote->isUsePerLineTva()) {
                    // TVA par ligne : utiliser le taux de la ligne source
                    $sourceTvaRate = $this->sourceLine->getTvaRate();
                    $tvaRate = ($sourceTvaRate && (float) $sourceTvaRate > 0) ? (float) $sourceTvaRate : null;
                } else {
                    // TVA globale : utiliser le taux global du devis
                    $quoteTvaRate = $quote->getTauxTVA();
                    $tvaRate = ($quoteTvaRate && (float) $quoteTvaRate > 0) ? (float) $quoteTvaRate : null;
                }
            }
        }
        
        // Si pas de taux depuis la source (ligne ajoutée), utiliser celui de la ligne d'avenant ou de l'avenant
        if ($tvaRate === null) {
            if ($this->tvaRate && (float) $this->tvaRate > 0) {
                $tvaRate = (float) $this->tvaRate;
            } elseif ($this->amendment && $this->amendment->getTauxTVA()) {
                $tvaRate = (float) $this->amendment->getTauxTVA();
            }
        }
        
        // Appliquer la TVA au delta HT pour obtenir le delta TTC
        // Si pas de TVA, le delta TTC = delta HT
        if ($tvaRate !== null && $tvaRate > 0) {
            $tvaAmount = $deltaHt * ($tvaRate / 100);
            $deltaTtc = $deltaHt + $tvaAmount;
        } else {
            // Pas de TVA : delta TTC = delta HT
            $deltaTtc = $deltaHt;
        }
        
        // S'assurer que le résultat est arrondi correctement
        $deltaTtc = round($deltaTtc, 2);
        return number_format($deltaTtc, 2, '.', '');
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
