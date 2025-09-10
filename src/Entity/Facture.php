<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
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
#[ORM\Table(name: 'factures')]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Patch(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['facture:read']],
    denormalizationContext: ['groups' => ['facture:write']],
    paginationItemsPerPage: 20
)]
class Facture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['facture:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    #[Groups(['facture:read', 'facture:write'])]
    #[Assert\Length(max: 50)]
    private ?string $numero = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['facture:read'])]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['facture:read', 'facture:write'])]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $dateEcheance = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['facture:read', 'facture:write'])]
    #[Assert\NotBlank]
    private ?string $statut = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['facture:read', 'facture:write'])]
    #[Assert\PositiveOrZero]
    private ?string $montantHT = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['facture:read', 'facture:write'])]
    #[Assert\PositiveOrZero]
    private ?string $montantTVA = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['facture:read', 'facture:write'])]
    #[Assert\PositiveOrZero]
    private ?string $montantTTC = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Groups(['facture:read', 'facture:write'])]
    #[Assert\PositiveOrZero]
    private ?string $montantAcompte = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['facture:read', 'facture:write'])]
    private ?string $conditionsPaiement = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['facture:read', 'facture:write'])]
    #[Assert\PositiveOrZero]
    private ?int $delaiPaiement = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Groups(['facture:read', 'facture:write'])]
    #[Assert\PositiveOrZero]
    private ?string $penalitesRetard = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['facture:read', 'facture:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['facture:read', 'facture:write'])]
    private ?\DateTimeInterface $datePaiement = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['facture:read', 'facture:write'])]
    private ?\DateTimeInterface $dateEnvoi = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['facture:read'])]
    private ?\DateTimeInterface $dateModification = null;

    // Relations
    #[ORM\OneToOne(targetEntity: Devis::class, inversedBy: 'facture')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['facture:read', 'facture:write'])]
    #[Assert\NotBlank]
    private ?Devis $devis = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'factures')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['facture:read', 'facture:write'])]
    #[Assert\NotBlank]
    private ?Client $client = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->dateModification = new \DateTime();
        $this->statut = FactureStatus::BROUILLON->value;
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

    public function getStatutEnum(): ?FactureStatus
    {
        return $this->statut ? FactureStatus::from($this->statut) : null;
    }

    public function setStatutEnum(FactureStatus $statut): self
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

    public function getDevis(): ?Devis
    {
        return $this->devis;
    }

    public function setDevis(?Devis $devis): self
    {
        $this->devis = $devis;
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
        if (!$this->dateEcheance || $this->statut === FactureStatus::PAYEE->value) {
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
        return $this->statut === FactureStatus::PAYEE->value;
    }

    /**
     * Vérifie si la facture peut être modifiée
     */
    public function canBeModified(): bool
    {
        return in_array($this->statut, [
            FactureStatus::BROUILLON->value,
            FactureStatus::ENVOYEE->value
        ]);
    }

    /**
     * Vérifie si la facture peut être annulée
     */
    public function canBeCancelled(): bool
    {
        return !in_array($this->statut, [
            FactureStatus::PAYEE->value,
            FactureStatus::ANNULEE->value
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
}
