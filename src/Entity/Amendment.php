<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Entité Amendment pour gérer les modifications des quotes émis
 * 
 * CONTRAINTE LÉGALE : Un amendment ne peut être créé que pour un quote existant.
 * Les invoices ne peuvent pas être modifiées par des amendments.
 */
#[ORM\Entity]
#[ORM\Table(name: 'amendments')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['amendment:read']],
    denormalizationContext: ['groups' => ['amendment:write']],
    paginationItemsPerPage: 20
)]
class Amendment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['amendment:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['amendment:read', 'amendment:write'])]
    #[Assert\NotBlank]
    private ?string $numero = null;

    // ===== RELATION OBLIGATOIRE AVEC UN DEVIS =====
    #[ORM\ManyToOne(targetEntity: Quote::class)]
    #[ORM\JoinColumn(name: 'quote_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['amendment:read', 'amendment:write'])]
    #[Assert\NotNull(message: "Un amendment doit être lié à un quote")]
    private ?Quote $quote = null;

    // ===== SYSTÈME DE TARIFS POUR L'AVENANT =====
    #[ORM\ManyToMany(targetEntity: Tariff::class)]
    #[ORM\JoinTable(name: 'amendment_tariffs')]
    #[Groups(['amendment:read', 'amendment:write'])]
    private Collection $tariffs;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['amendment:read', 'amendment:write'])]
    #[Assert\NotBlank]
    private ?string $motif = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['amendment:read', 'amendment:write'])]
    #[Assert\NotBlank]
    private ?string $modifications = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['amendment:read', 'amendment:write'])]
    private ?string $justification = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['amendment:read'])]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['amendment:read', 'amendment:write'])]
    private ?\DateTimeInterface $dateValidation = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['amendment:read', 'amendment:write'])]
    #[Assert\NotBlank]
    private ?string $statut = 'brouillon'; // brouillon, valide, rejete, envoye

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['amendment:read', 'amendment:write'])]
    private ?string $notes = null;

    #[ORM\Column(length: 36)]
    #[Assert\NotBlank(message: 'Le company_id est obligatoire')]
    #[Groups(['amendment:read', 'amendment:write'])]
    private ?string $companyId = null;

    // ===== NOUVEAUX CHAMPS POUR LES MONTANTS DE L'AVENANT =====

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['amendment:read', 'amendment:write'])]
    private ?int $montantHT = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['amendment:read', 'amendment:write'])]
    private ?int $montantTVA = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['amendment:read', 'amendment:write'])]
    private ?int $montantTTC = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    #[Groups(['amendment:read', 'amendment:write'])]
    private ?string $tauxTVA = '0.00';

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->statut = 'brouillon';
        $this->tariffs = new ArrayCollection();
        $this->montantHT = 0;
        $this->montantTVA = 0;
        $this->montantTTC = 0;
        $this->tauxTVA = '0.00';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): static
    {
        $this->numero = $numero;
        return $this;
    }

    public function getQuote(): ?Quote
    {
        return $this->quote;
    }

    public function setQuote(?Quote $quote): static
    {
        $this->quote = $quote;

        // Propager automatiquement le tauxTVA du quote vers l'amendment
        if ($quote && $quote->getTauxTVA()) {
            $this->setTauxTVA($quote->getTauxTVA());
        }

        return $this;
    }

    /**
     * @return Collection<int, Tariff>
     */
    public function getTariffs(): Collection
    {
        return $this->tariffs;
    }

    public function addTariff(Tariff $tariff): static
    {
        if (!$this->tariffs->contains($tariff)) {
            $this->tariffs->add($tariff);
            $this->calculerMontantsDepuisTarifs();
        }
        return $this;
    }

    public function removeTariff(Tariff $tariff): static
    {
        if ($this->tariffs->removeElement($tariff)) {
            $this->calculerMontantsDepuisTarifs();
        }
        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(string $motif): static
    {
        $this->motif = $motif;
        return $this;
    }

    public function getModifications(): ?string
    {
        return $this->modifications;
    }

    public function setModifications(string $modifications): static
    {
        $this->modifications = $modifications;
        return $this;
    }

    public function getJustification(): ?string
    {
        return $this->justification;
    }

    public function setJustification(?string $justification): static
    {
        $this->justification = $justification;
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

    public function getDateValidation(): ?\DateTimeInterface
    {
        return $this->dateValidation;
    }

    public function setDateValidation(?\DateTimeInterface $dateValidation): static
    {
        $this->dateValidation = $dateValidation;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    // ===== NOUVELLES MÉTHODES POUR LES MONTANTS =====

    public function getMontantHT(): ?int
    {
        return $this->montantHT;
    }

    public function setMontantHT(int $montantHT): static
    {
        $this->montantHT = $montantHT;
        return $this;
    }

    public function getMontantTVA(): ?int
    {
        return $this->montantTVA;
    }

    public function setMontantTVA(int $montantTVA): static
    {
        $this->montantTVA = $montantTVA;
        return $this;
    }

    public function getMontantTTC(): ?int
    {
        return $this->montantTTC;
    }

    public function setMontantTTC(int $montantTTC): static
    {
        $this->montantTTC = $montantTTC;
        return $this;
    }

    public function getTauxTVA(): ?string
    {
        return $this->tauxTVA;
    }

    public function setTauxTVA(string $tauxTVA): static
    {
        $this->tauxTVA = $tauxTVA;
        return $this;
    }

    // ===== MÉTHODES DE CALCUL AUTOMATIQUE =====

    /**
     * Calcule les montants automatiquement depuis les tarifs sélectionnés
     */
    public function calculerMontantsDepuisTarifs(): void
    {
        // On travaille en centimes pour éviter les flottants
        $montantHTCents = 0;

        foreach ($this->tariffs as $tariff) {
            // getPrix() renvoie le prix stocké (attendu en centimes). On force le cast en entier.
            $montantHTCents += (int) $tariff->getPrix();
        }

        // Affectations strictes en int
        $this->montantHT = (int) $montantHTCents;

        // Calcul de la TVA à partir d'un pourcentage stocké en chaîne (ex: "20.00")
        $tauxTVAPourcentage = (float) $this->tauxTVA; // ex: 20.00
        $this->montantTVA = (int) round($this->montantHT * $tauxTVAPourcentage / 100);

        // Calcul du TTC
        $this->montantTTC = (int) ($this->montantHT + $this->montantTVA);
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
     * Retourne le montant TTC formaté
     */
    public function getMontantTTCFormate(): string
    {
        $montant = (float) $this->montantTTC / 100; // Conversion centimes -> euros
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    // ===== MÉTHODES DE STATUT =====

    public function isBrouillon(): bool
    {
        return $this->statut === 'brouillon';
    }

    public function isValide(): bool
    {
        return $this->statut === 'valide';
    }

    public function isRejete(): bool
    {
        return $this->statut === 'rejete';
    }

    public function isEnvoye(): bool
    {
        return $this->statut === 'envoye';
    }

    public function getStatutLabel(): string
    {
        return match ($this->statut) {
            'brouillon' => 'Brouillon',
            'valide' => 'Validé',
            'rejete' => 'Rejeté',
            'envoye' => 'Envoyé',
            default => 'Inconnu'
        };
    }

    public function getStatutColor(): string
    {
        return match ($this->statut) {
            'brouillon' => 'warning',
            'valide' => 'success',
            'rejete' => 'danger',
            'envoye' => 'info',
            default => 'secondary'
        };
    }

    // ===== MÉTHODES D'AFFICHAGE =====

    public function getDocumentInfo(): string
    {
        if (!$this->quote) {
            return 'Devis inconnu';
        }

        return sprintf(
            'Quote %s - %s (%s)',
            $this->quote->getNumero(),
            $this->quote->getClient()?->getNomComplet() ?? 'Client inconnu',
            $this->quote->getMontantTTCFormate()
        );
    }

    public function __toString(): string
    {
        return $this->numero ?? 'Amendment #' . $this->id;
    }

    /**
     * Résumé lisible des tarifs du devis concerné (pour affichage admin/PDF)
     */
    public function getDevisTarifsResume(): string
    {
        if ($this->quote === null) {
            return '';
        }

        $tariffs = $this->quote->getTariffs();
        if ($tariffs === null || $tariffs->count() === 0) {
            return 'Aucun tarif sur le devis d\'origine.';
        }

        $lines = [];
        foreach ($tariffs as $tariff) {
            $nom = method_exists($tariff, 'getNom') ? (string) $tariff->getNom() : '';
            $prix = method_exists($tariff, 'getPrixTTCFormate') ? (string) $tariff->getPrixTTCFormate() : '';
            $lines[] = trim($nom . ' — ' . $prix);
        }

        return implode("\n", $lines);
    }
}
