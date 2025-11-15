<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CreditNoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;

/**
 * Entité CreditNote pour gérer les avoirs sur factures émises
 * 
 * CONTRAINTE LÉGALE :
 * - Un avoir doit obligatoirement référencer une facture émise (ISSUED/SENT)
 * - Un avoir émis devient immuable
 * - Archivage 10 ans obligatoire
 * - Un avoir ne supprime jamais la facture d'origine
 */
#[ORM\Entity(repositoryClass: CreditNoteRepository::class)]
#[ORM\Table(name: 'credit_notes')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            security: "is_granted('ROLE_USER') && object.getInvoice() !== null && object.getInvoice().getStatutEnum() !== null && object.getInvoice().getStatutEnum().isEmitted() && object.getInvoice().getStatutEnum().value !== 'cancelled'",
            securityMessage: "Seuls les utilisateurs authentifiés peuvent créer des avoirs via l'API. L'avoir doit être associé à une facture émise et non annulée."
        ),
        new Put(
            security: "object.getStatutEnum() !== null && object.getStatutEnum().isModifiable()",
            securityMessage: "Seuls les avoirs en brouillon peuvent être modifiés via l'API."
        ),
        new Delete(
            security: "false",
            securityMessage: "Les avoirs ne peuvent pas être supprimés."
        )
    ],
    normalizationContext: ['groups' => ['credit_note:read']],
    denormalizationContext: ['groups' => ['credit_note:write']],
    paginationItemsPerPage: 20
)]
class CreditNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['credit_note:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true, nullable: true)]
    #[Assert\Length(max: 30, maxMessage: 'Le numéro ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    private ?string $number = null;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 20, enumType: CreditNoteStatus::class)]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    #[Assert\NotNull(message: "Le statut est obligatoire")]
    private ?CreditNoteStatus $statut = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    #[Assert\NotBlank(message: "Le motif est obligatoire")]
    private ?string $reason = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['credit_note:read'])]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['credit_note:read'])]
    private ?\DateTimeInterface $dateEmission = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['credit_note:read'])]
    private ?\DateTimeInterface $dateModification = null;

    // ===== MONTANTS EN DECIMAL (EUROS) =====
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    #[Assert\NotBlank(message: 'Le montant HT est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le montant HT doit être un nombre')]
    private string $montantHT = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    #[Assert\NotBlank(message: 'Le montant TVA est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le montant TVA doit être un nombre')]
    private string $montantTVA = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    #[Assert\NotBlank(message: 'Le montant TTC est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le montant TTC doit être un nombre')]
    private string $montantTTC = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    private ?string $tauxTVA = null;

    #[ORM\Column(length: 36)]
    #[Assert\NotBlank(message: 'Le company_id est obligatoire')]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    private ?string $companyId = null;

    #[ORM\ManyToOne(inversedBy: 'creditNotes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'avoir doit être lié à une facture')]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    private ?Invoice $invoice = null;

    /**
     * @var Collection<int, CreditNoteLine>
     */
    #[ORM\OneToMany(targetEntity: CreditNoteLine::class, mappedBy: 'creditNote', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    private Collection $lines;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->dateModification = new \DateTime();
        $this->statut = CreditNoteStatus::DRAFT;
        $this->montantHT = '0.00';
        $this->montantTVA = '0.00';
        $this->montantTTC = '0.00';
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        // Empêcher la modification du numéro si l'avoir est émis
        if ($this->id !== null && $this->number !== null && $this->number !== $number) {
            $statutEnum = $this->getStatutEnum();
            if ($statutEnum && $statutEnum->isEmitted()) {
                throw new \RuntimeException(
                    sprintf(
                        'Le numéro de l\'avoir #%s ne peut pas être modifié car il est déjà émis.',
                        $this->number
                    )
                );
            }
        }

        $this->number = $number;
        return $this;
    }

    public function getStatut(): ?CreditNoteStatus
    {
        return $this->statut;
    }

    public function setStatut(?CreditNoteStatus $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getStatutEnum(): ?CreditNoteStatus
    {
        return $this->statut;
    }

    public function setStatutEnum(CreditNoteStatus $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    // Compatibilité avec l'ancien code (string)
    public function getStatus(): ?string
    {
        return $this->statut?->value;
    }

    public function setStatus(string $status): static
    {
        $this->statut = CreditNoteStatus::from($status);
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDateEmission(): ?\DateTimeInterface
    {
        return $this->dateEmission;
    }

    public function setDateEmission(?\DateTimeInterface $dateEmission): static
    {
        $this->dateEmission = $dateEmission;
        return $this;
    }

    public function getDateModification(): ?\DateTimeInterface
    {
        return $this->dateModification;
    }

    public function setDateModification(?\DateTimeInterface $dateModification): static
    {
        $this->dateModification = $dateModification;
        return $this;
    }

    // ===== MÉTHODES POUR LES MONTANTS (DECIMAL EN EUROS) =====

    public function getMontantHT(): string
    {
        return $this->montantHT;
    }

    public function setMontantHT(string $montantHT): static
    {
        $this->montantHT = $montantHT;
        return $this;
    }

    public function getMontantTVA(): string
    {
        return $this->montantTVA;
    }

    public function setMontantTVA(string $montantTVA): static
    {
        $this->montantTVA = $montantTVA;
        return $this;
    }

    public function getMontantTTC(): string
    {
        return $this->montantTTC;
    }

    public function setMontantTTC(string $montantTTC): static
    {
        $this->montantTTC = $montantTTC;
        return $this;
    }

    public function getTauxTVA(): ?string
    {
        return $this->tauxTVA;
    }

    public function setTauxTVA(?string $tauxTVA): static
    {
        $this->tauxTVA = $tauxTVA;
        return $this;
    }

    public function getCompanyId(): ?string
    {
        return $this->companyId;
    }

    public function setCompanyId(string $companyId): static
    {
        $this->companyId = $companyId;
        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        // Empêcher le changement de facture si l'avoir est émis
        if ($this->id !== null && $this->invoice !== null && $this->invoice !== $invoice) {
            $statutEnum = $this->getStatutEnum();
            if ($statutEnum && $statutEnum->isEmitted()) {
                throw new \RuntimeException(
                    sprintf(
                        'La facture associée à l\'avoir #%s ne peut pas être modifiée car il est déjà émis.',
                        $this->number ?? $this->id
                    )
                );
            }
        }

        $this->invoice = $invoice;
        return $this;
    }

    /**
     * @return Collection<int, CreditNoteLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(CreditNoteLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setCreditNote($this);
        }
        return $this;
    }

    public function removeLine(CreditNoteLine $line): static
    {
        if ($this->lines->removeElement($line)) {
            if ($line->getCreditNote() === $this) {
                $line->setCreditNote(null);
            }
        }
        return $this;
    }

    /**
     * Recalcule les montants HT, TVA et TTC à partir des lignes
     * Les montants sont stockés en DECIMAL (euros)
     */
    public function recalculateTotals(): void
    {
        if ($this->lines->isEmpty()) {
            $this->montantHT = '0.00';
            $this->montantTVA = '0.00';
            $this->montantTTC = '0.00';
            return;
        }

        $totalHtEuros = 0.0;
        $totalTvaEuros = 0.0;

        foreach ($this->lines as $line) {
            $lineTotalHt = (float) ($line->getTotalHt() ?? 0);
            $totalHtEuros += $lineTotalHt;

            if ($line->getTvaRate() && (float) $line->getTvaRate() > 0) {
                // Pour un avoir, la TVA est également négative si le montant HT est négatif
                $tvaAmount = abs($lineTotalHt) * ((float) $line->getTvaRate() / 100);
                // Conserver le signe du montant HT
                if ($lineTotalHt < 0) {
                    $tvaAmount = -$tvaAmount;
                }
                $totalTvaEuros += $tvaAmount;
            }
        }

        $this->montantHT = number_format($totalHtEuros, 2, '.', '');
        $this->montantTVA = number_format($totalTvaEuros, 2, '.', '');
        $this->montantTTC = number_format($totalHtEuros + $totalTvaEuros, 2, '.', '');
    }

    /**
     * Valide que l'avoir peut être émis
     * 
     * @throws \RuntimeException si l'avoir ne peut pas être émis
     */
    public function validateCanBeIssued(): void
    {
        // Vérifier qu'au moins une ligne est présente
        if ($this->lines->isEmpty()) {
            throw new \RuntimeException('Un avoir ne peut pas être émis sans ligne.');
        }

        // Vérifier que le montant HT n'est pas nul (peut être négatif pour un avoir)
        if ((float) $this->montantHT == 0) {
            throw new \RuntimeException('Un avoir doit avoir un montant HT différent de 0.');
        }

        // Vérifier que le motif est renseigné
        if (empty($this->reason)) {
            throw new \RuntimeException('Un avoir ne peut pas être émis sans motif.');
        }

        // Vérifier que la facture associée est émise
        if (!$this->invoice) {
            throw new \RuntimeException('Un avoir doit être lié à une facture.');
        }

        $invoiceStatut = $this->invoice->getStatutEnum();
        if (!$invoiceStatut || !$invoiceStatut->isEmitted()) {
            throw new \RuntimeException('Un avoir ne peut être créé que pour une facture émise.');
        }
        
        // Vérifier que la facture n'est pas annulée
        if ($invoiceStatut === \App\Entity\InvoiceStatus::CANCELLED) {
            throw new \RuntimeException('Un avoir ne peut pas être créé pour une facture annulée.');
        }

        // Vérifier qu'on ne crée pas un deuxième avoir total
        $totalAvoirs = 0.0;
        foreach ($this->invoice->getCreditNotes() as $existingCreditNote) {
            if ($existingCreditNote->getId() !== $this->id) {
                $totalAvoirs += (float) $existingCreditNote->getMontantTTC();
            }
        }
        $totalAvoirs += (float) $this->montantTTC;

        if ($totalAvoirs > (float) $this->invoice->getMontantTTC()) {
            throw new \RuntimeException(
                sprintf(
                    'Le total des avoirs (%.2f €) ne peut pas dépasser le montant TTC de la facture (%.2f €).',
                    $totalAvoirs,
                    (float) $this->invoice->getMontantTTC()
                )
            );
        }
    }

    // ===== MÉTHODES DE STATUT =====

    public function canBeModified(): bool
    {
        return $this->statut && $this->statut->isModifiable();
    }

    public function canBeIssued(): bool
    {
        return $this->statut === CreditNoteStatus::DRAFT;
    }

    public function getStatutLabel(): string
    {
        return $this->statut?->getLabel() ?? 'Inconnu';
    }

    public function getStatutColor(): string
    {
        return $this->statut?->getColor() ?? 'secondary';
    }

    /**
     * Vérifie si l'avoir annule complètement la facture
     */
    public function isTotal(): bool
    {
        if (!$this->invoice) {
            return false;
        }

        $montantAvoir = (float) $this->montantTTC;
        $montantFacture = (float) $this->invoice->getMontantTTC();

        // Tolérance de 0.01 € pour les arrondis
        return abs($montantAvoir - $montantFacture) < 0.01;
    }

    /**
     * Retourne le montant HT formaté pour l'affichage
     */
    public function getMontantHTFormatted(): string
    {
        $montant = (float) $this->montantHT; // Déjà en euros (DECIMAL)
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Retourne le montant TVA formaté pour l'affichage
     */
    public function getMontantTVAFormatted(): string
    {
        $montant = (float) $this->montantTVA; // Déjà en euros (DECIMAL)
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Retourne le montant TTC formaté pour l'affichage
     */
    public function getMontantTTCFormatted(): string
    {
        $montant = (float) $this->montantTTC; // Déjà en euros (DECIMAL)
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Représentation string de l'avoir
     */
    public function __toString(): string
    {
        return sprintf('%s - %s', $this->number ?? 'Avoir #' . $this->id, $this->getMontantTTCFormatted());
    }

    // ===== LIFECYCLE CALLBACKS =====

    #[ORM\PrePersist]
    public function setDateCreationValue(): void
    {
        if (!$this->dateCreation) {
            $this->dateCreation = new \DateTime();
        }
        $this->dateModification = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setDateModificationValue(): void
    {
        $this->dateModification = new \DateTime();
    }
}
