<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;

#[ORM\Entity]
#[ORM\Table(name: 'invoices')]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Patch(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['invoice:read']],
    denormalizationContext: ['groups' => ['invoice:write']],
    paginationItemsPerPage: 20
)]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['invoice:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\Length(max: 50)]
    private ?string $numero = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['invoice:read'])]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $dateEcheance = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\NotBlank]
    private ?string $statut = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\PositiveOrZero]
    private ?string $montantHT = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\PositiveOrZero]
    private ?string $montantTVA = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\PositiveOrZero]
    private ?string $montantTTC = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\PositiveOrZero]
    private ?string $montantAcompte = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $conditionsPaiement = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\PositiveOrZero]
    private ?int $delaiPaiement = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\PositiveOrZero]
    private ?string $penalitesRetard = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?\DateTimeInterface $datePaiement = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?\DateTimeInterface $dateEnvoi = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?\DateTimeInterface $dateModification = null;

    #[ORM\Column(length: 36)]
    #[Assert\NotBlank(message: 'Le company_id est obligatoire')]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $companyId = null;

    // ===== CHAMPS PDP (Plateforme de Dématérialisation Partenaire) =====

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $pdpStatus = null; // ACCEPTED, REJECTED, PENDING

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $pdpProvider = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?\DateTimeInterface $pdpTransmissionDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $pdpResponse = null; // Réponse de la PDP (JSON ou texte)

    // Relations
    #[ORM\OneToOne(targetEntity: Quote::class, inversedBy: 'invoice')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\NotBlank]
    private ?Quote $quote = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'invoices')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\NotBlank]
    private ?Client $client = null;

    /**
     * @var Collection<int, InvoiceLine>
     */
    #[ORM\OneToMany(targetEntity: InvoiceLine::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private Collection $lines;

    /**
     * @var Collection<int, CreditNote>
     */
    #[ORM\OneToMany(targetEntity: CreditNote::class, mappedBy: 'invoice')]
    #[Groups(['invoice:read'])]
    private Collection $creditNotes;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->dateModification = new \DateTime();
        $this->statut = InvoiceStatus::DRAFT->value;
        $this->lines = new ArrayCollection();
        $this->creditNotes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): self
    {
        $this->numero = $numero;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): self
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDateEcheance(): ?\DateTimeInterface
    {
        return $this->dateEcheance;
    }

    public function setDateEcheance(\DateTimeInterface $dateEcheance): self
    {
        $this->dateEcheance = $dateEcheance;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        $this->dateModification = new \DateTime();
        return $this;
    }

    public function getStatutEnum(): ?InvoiceStatus
    {
        return $this->statut ? InvoiceStatus::from($this->statut) : null;
    }

    public function setStatutEnum(InvoiceStatus $statut): self
    {
        $this->statut = $statut->value;
        $this->dateModification = new \DateTime();
        return $this;
    }

    public function getMontantHT(): ?string
    {
        return $this->montantHT;
    }

    public function setMontantHT(string $montantHT): self
    {
        $this->montantHT = $montantHT;
        return $this;
    }

    public function getMontantTVA(): ?string
    {
        return $this->montantTVA;
    }

    public function setMontantTVA(string $montantTVA): self
    {
        $this->montantTVA = $montantTVA;
        return $this;
    }

    public function getMontantTTC(): ?string
    {
        return $this->montantTTC;
    }

    public function setMontantTTC(string $montantTTC): self
    {
        $this->montantTTC = $montantTTC;
        return $this;
    }

    public function getMontantAcompte(): ?string
    {
        return $this->montantAcompte;
    }

    public function setMontantAcompte(?string $montantAcompte): self
    {
        $this->montantAcompte = $montantAcompte;
        return $this;
    }

    public function getConditionsPaiement(): ?string
    {
        return $this->conditionsPaiement;
    }

    public function setConditionsPaiement(?string $conditionsPaiement): self
    {
        $this->conditionsPaiement = $conditionsPaiement;
        return $this;
    }

    public function getDelaiPaiement(): ?int
    {
        return $this->delaiPaiement;
    }

    public function setDelaiPaiement(?int $delaiPaiement): self
    {
        $this->delaiPaiement = $delaiPaiement;
        return $this;
    }

    public function getPenalitesRetard(): ?string
    {
        return $this->penalitesRetard;
    }

    public function setPenalitesRetard(?string $penalitesRetard): self
    {
        $this->penalitesRetard = $penalitesRetard;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getDatePaiement(): ?\DateTimeInterface
    {
        return $this->datePaiement;
    }

    public function setDatePaiement(?\DateTimeInterface $datePaiement): self
    {
        $this->datePaiement = $datePaiement;
        return $this;
    }

    public function getDateEnvoi(): ?\DateTimeInterface
    {
        return $this->dateEnvoi;
    }

    public function setDateEnvoi(?\DateTimeInterface $dateEnvoi): self
    {
        $this->dateEnvoi = $dateEnvoi;
        return $this;
    }

    public function getDateModification(): ?\DateTimeInterface
    {
        return $this->dateModification;
    }

    public function setDateModification(?\DateTimeInterface $dateModification): self
    {
        $this->dateModification = $dateModification;
        return $this;
    }

    public function getCompanyId(): ?string
    {
        return $this->companyId;
    }

    public function setCompanyId(string $companyId): self
    {
        $this->companyId = $companyId;
        return $this;
    }

    public function getPdpStatus(): ?string
    {
        return $this->pdpStatus;
    }

    public function setPdpStatus(?string $pdpStatus): self
    {
        $this->pdpStatus = $pdpStatus;
        return $this;
    }

    public function getPdpProvider(): ?string
    {
        return $this->pdpProvider;
    }

    public function setPdpProvider(?string $pdpProvider): self
    {
        $this->pdpProvider = $pdpProvider;
        return $this;
    }

    public function getPdpTransmissionDate(): ?\DateTimeInterface
    {
        return $this->pdpTransmissionDate;
    }

    public function setPdpTransmissionDate(?\DateTimeInterface $pdpTransmissionDate): self
    {
        $this->pdpTransmissionDate = $pdpTransmissionDate;
        return $this;
    }

    public function getPdpResponse(): ?string
    {
        return $this->pdpResponse;
    }

    public function setPdpResponse(?string $pdpResponse): self
    {
        $this->pdpResponse = $pdpResponse;
        return $this;
    }

    public function getQuote(): ?Quote
    {
        return $this->quote;
    }

    public function setQuote(?Quote $quote): self
    {
        $this->quote = $quote;
        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;
        return $this;
    }

    // ===== MÉTHODES MÉTIER =====

    /**
     * Retourne le montant restant à payer
     */
    public function getMontantRestant(): string
    {
        $montantTTC = (float) $this->montantTTC / 100; // Conversion centimes -> euros
        $montantAcompte = (float) ($this->montantAcompte ?? 0) / 100; // Conversion centimes -> euros

        return number_format($montantTTC - $montantAcompte, 2, '.', '');
    }

    /**
     * Vérifie si la facture est en retard
     */
    public function isEnRetard(): bool
    {
        if (!$this->dateEcheance || $this->statut === InvoiceStatus::PAID->value) {
            return false;
        }

        return new \DateTime() > $this->dateEcheance;
    }

    /**
     * Retourne le nombre de jours de retard
     */
    public function getJoursRetard(): int
    {
        if (!$this->isEnRetard()) {
            return 0;
        }

        $now = new \DateTime();
        $diff = $now->diff($this->dateEcheance);

        return $diff->days;
    }

    /**
     * Calcule les pénalités de retard
     */
    public function getMontantPenalites(): string
    {
        if (!$this->isEnRetard() || !$this->penalitesRetard) {
            return '0.00';
        }

        $montantRestant = (float) $this->getMontantRestant();
        $tauxPenalites = (float) $this->penalitesRetard;
        $joursRetard = $this->getJoursRetard();

        $penalites = ($montantRestant * $tauxPenalites / 100) * $joursRetard;

        return number_format($penalites, 2, '.', '');
    }

    /**
     * Vérifie si la facture est payée intégralement
     */
    public function isPayee(): bool
    {
        return $this->statut === InvoiceStatus::PAID->value;
    }

    /**
     * Vérifie si la facture peut être modifiée
     */
    public function canBeModified(): bool
    {
        return in_array($this->statut, [
            InvoiceStatus::DRAFT->value,
            InvoiceStatus::SENT->value
        ]);
    }

    /**
     * Vérifie si la facture peut être annulée
     */
    public function canBeCancelled(): bool
    {
        return !in_array($this->statut, [
            InvoiceStatus::PAID->value,
            InvoiceStatus::CANCELLED->value
        ]);
    }

    /**
     * Retourne le statut formaté pour l'affichage
     */
    public function getStatutLabel(): string
    {
        $statutEnum = $this->getStatutEnum();
        return $statutEnum ? $statutEnum->getLabel() : 'Non défini';
    }

    /**
     * Retourne la couleur du statut pour l'affichage
     */
    public function getStatutColor(): string
    {
        $statutEnum = $this->getStatutEnum();
        return $statutEnum ? $statutEnum->getColor() : 'secondary';
    }

    /**
     * Retourne le montant TTC formaté
     */
    public function getMontantTTCFormate(): string
    {
        $montant = (float) $this->montantTTC / 100; // Conversion centimes -> euros
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Retourne le montant HT formaté
     */
    public function getMontantHTFormate(): string
    {
        $montant = (float) $this->montantHT / 100; // Conversion centimes -> euros
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Retourne le montant TVA formaté
     */
    public function getMontantTVAFormate(): string
    {
        $montant = (float) $this->montantTVA / 100; // Conversion centimes -> euros
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Retourne le montant restant formaté
     */
    public function getMontantRestantFormate(): string
    {
        return number_format((float) $this->getMontantRestant(), 2, ',', ' ') . ' €';
    }

    /**
     * Représentation string de la facture pour les listes déroulantes
     */
    public function __toString(): string
    {
        $client = $this->getClient() ? $this->getClient()->getNomComplet() : 'Client inconnu';
        return sprintf('%s - %s (%s)', $this->numero ?? 'Invoice #' . $this->id, $client, $this->getMontantTTCFormate());
    }

    /**
     * @return Collection<int, InvoiceLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(InvoiceLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setInvoice($this);
        }

        return $this;
    }

    public function removeLine(InvoiceLine $line): static
    {
        if ($this->lines->removeElement($line)) {
            // set the owning side to null (unless already changed)
            if ($line->getInvoice() === $this) {
                $line->setInvoice(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CreditNote>
     */
    public function getCreditNotes(): Collection
    {
        return $this->creditNotes;
    }

    public function addCreditNote(CreditNote $creditNote): static
    {
        if (!$this->creditNotes->contains($creditNote)) {
            $this->creditNotes->add($creditNote);
            $creditNote->setInvoice($this);
        }

        return $this;
    }

    public function removeCreditNote(CreditNote $creditNote): static
    {
        if ($this->creditNotes->removeElement($creditNote)) {
            // set the owning side to null (unless already changed)
            if ($creditNote->getInvoice() === $this) {
                $creditNote->setInvoice(null);
            }
        }

        return $this;
    }
}
