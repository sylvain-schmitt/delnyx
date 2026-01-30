<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TariffRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Entité Tarif - Grille tarifaire des services Delnyx
 */
#[ORM\Entity(repositoryClass: TariffRepository::class)]
#[ORM\Table(name: 'tariffs')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            paginationEnabled: true,
            paginationItemsPerPage: 50,
            normalizationContext: ['groups' => ['tariff:read']]
        ),
        new Get(normalizationContext: ['groups' => ['tariff:read', 'tariff:detail']]),
        new Post(denormalizationContext: ['groups' => ['tariff:write']]),
        new Put(denormalizationContext: ['groups' => ['tariff:write']]),
        new Patch(denormalizationContext: ['groups' => ['tariff:write']]),
        new Delete()
    ],
    normalizationContext: ['groups' => ['tariff:read']],
    denormalizationContext: ['groups' => ['tariff:write']]
)]
class Tariff
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['tariff:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'Le nom du tarif est obligatoire')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le nom doit contenir au moins {{ limit }} caractères', maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['tariff:read', 'tariff:write'])]
    private ?string $nom = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank(message: 'La catégorie est obligatoire')]
    #[Assert\Choice(choices: ['site_vitrine', 'reservation', 'application_gestion', 'maintenance'], message: 'La catégorie doit être sélectionnée')]
    #[Groups(['tariff:read', 'tariff:write'])]
    private ?string $categorie = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['tariff:read', 'tariff:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le prix est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le prix doit être un nombre')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le prix ne peut pas être négatif')]
    #[Groups(['tariff:read', 'tariff:write'])]
    private ?string $prix = null;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'forfait'])]
    #[Assert\NotBlank(message: 'L\'unité est obligatoire')]
    #[Assert\Choice(choices: ['forfait', 'mois', 'an', 'heure'], message: 'L\'unité doit être sélectionnée')]
    #[Groups(['tariff:read', 'tariff:write'])]
    private string $unite = 'forfait';

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    #[Groups(['tariff:read', 'tariff:write'])]
    private bool $actif = true;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['tariff:read', 'tariff:write'])]
    private int $ordre = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['tariff:read', 'tariff:write'])]
    private ?string $caracteristiques = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['tariff:read', 'tariff:write'])]
    private bool $hasRecurrence = false;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Groups(['tariff:read', 'tariff:write'])]
    private ?string $prixMensuel = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Groups(['tariff:read', 'tariff:write'])]
    private ?string $prixAnnuel = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['tariff:read', 'tariff:write'])]
    private ?string $stripeProductId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['tariff:read', 'tariff:write'])]
    private ?string $stripePriceIdMonthly = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['tariff:read', 'tariff:write'])]
    private ?string $stripePriceIdYearly = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['tariff:read'])]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['tariff:read'])]
    private ?\DateTimeInterface $dateModification = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->dateModification = new \DateTime();
    }

    // ===== GETTERS & SETTERS =====

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    /**
     * Alias pour getNom() utilisé dans les entités Line
     */
    public function getTitre(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): self
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(?string $prix): self
    {
        $this->prix = $prix;
        return $this;
    }

    public function getUnite(): string
    {
        return $this->unite;
    }

    public function setUnite(string $unite): self
    {
        $this->unite = $unite;
        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;
        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): self
    {
        $this->ordre = $ordre;
        return $this;
    }

    public function getCaracteristiques(): ?string
    {
        return $this->caracteristiques;
    }

    public function setCaracteristiques(?string $caracteristiques): self
    {
        $this->caracteristiques = $caracteristiques;
        return $this;
    }

    public function isHasRecurrence(): bool
    {
        return $this->hasRecurrence;
    }

    public function setHasRecurrence(bool $hasRecurrence): self
    {
        $this->hasRecurrence = $hasRecurrence;
        return $this;
    }

    public function getPrixMensuel(): ?string
    {
        return $this->prixMensuel;
    }

    public function setPrixMensuel(?string $prixMensuel): self
    {
        $this->prixMensuel = $prixMensuel;
        return $this;
    }

    public function getPrixAnnuel(): ?string
    {
        return $this->prixAnnuel;
    }

    public function setPrixAnnuel(?string $prixAnnuel): self
    {
        $this->prixAnnuel = $prixAnnuel;
        return $this;
    }

    public function getStripeProductId(): ?string
    {
        return $this->stripeProductId;
    }

    public function setStripeProductId(?string $stripeProductId): self
    {
        $this->stripeProductId = $stripeProductId;
        return $this;
    }

    public function getStripePriceIdMonthly(): ?string
    {
        return $this->stripePriceIdMonthly;
    }

    public function setStripePriceIdMonthly(?string $stripePriceIdMonthly): self
    {
        $this->stripePriceIdMonthly = $stripePriceIdMonthly;
        return $this;
    }

    public function getStripePriceIdYearly(): ?string
    {
        return $this->stripePriceIdYearly;
    }

    public function setStripePriceIdYearly(?string $stripePriceIdYearly): self
    {
        $this->stripePriceIdYearly = $stripePriceIdYearly;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(?\DateTimeInterface $dateCreation): self
    {
        $this->dateCreation = $dateCreation;
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

    // ===== MÉTHODES UTILITAIRES =====

    /**
     * Retourne le libellé de la catégorie
     */
    #[Groups(['tariff:read'])]
    public function getCategorieLabel(): string
    {
        return match ($this->categorie) {
            'site_vitrine' => 'Site vitrine',
            'reservation' => 'Système de réservation',
            'application_gestion' => 'Application de gestion',
            'maintenance' => 'Maintenance & Support',
            default => 'Autre'
        };
    }

    /**
     * Retourne le libellé de l'unité
     */
    #[Groups(['tariff:read'])]
    public function getUniteLabel(): string
    {
        return match ($this->unite) {
            'forfait' => 'Forfait',
            'mois' => 'Par mois',
            'an' => 'Par an',
            'heure' => 'Par heure',
            default => 'Forfait'
        };
    }

    /**
     * Retourne le prix formaté avec l'unité
     */
    #[Groups(['tariff:read'])]
    public function getPrixFormate(): string
    {
        // Le prix est déjà stocké en décimal (euros) dans la base
        $prixEnEuros = (float) $this->prix;
        $prix = number_format($prixEnEuros, 2, ',', ' ');

        return match ($this->unite) {
            'forfait' => $prix . ' €',
            'mois' => $prix . ' €/mois',
            'an' => $prix . ' €/an',
            'heure' => $prix . ' €/h',
            default => $prix . ' €'
        };
    }

    /**
     * Retourne le prix TTC formaté avec l'unité
     */
    #[Groups(['tariff:read'])]
    public function getPrixTTCFormate(): string
    {
        // Calculer le prix TTC (HT + TVA)
        $prixHT = (float) $this->prix;
        $tauxTVA = 0.20; // 20% par défaut, à adapter selon vos besoins
        $prixTTC = $prixHT * (1 + $tauxTVA);
        $prix = number_format($prixTTC, 2, ',', ' ');

        return match ($this->unite) {
            'forfait' => $prix . ' € TTC',
            'mois' => $prix . ' €/mois TTC',
            'an' => $prix . ' €/an TTC',
            'heure' => $prix . ' €/h TTC',
            default => $prix . ' € TTC'
        };
    }

    /**
     * Retourne toutes les catégories disponibles
     */
    public static function getCategories(): array
    {
        return [
            'site_vitrine' => 'Site vitrine',
            'reservation' => 'Système de réservation',
            'application_gestion' => 'Application de gestion',
            'maintenance' => 'Maintenance & Support'
        ];
    }

    /**
     * Retourne toutes les unités disponibles
     */
    public static function getUnites(): array
    {
        return [
            'forfait' => 'Forfait',
            'mois' => 'Par mois',
            'an' => 'Par an',
            'heure' => 'Par heure'
        ];
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function setDateModificationValue(): void
    {
        $this->dateModification = new \DateTime();
    }

    public function __toString(): string
    {
        return $this->getNom() ?? 'Nouveau tarif';
    }
}
