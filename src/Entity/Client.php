<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'clients')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Un client avec cet email existe déjà.')]
#[ApiResource(
    operations: [
        new GetCollection(
            paginationEnabled: true,
            paginationItemsPerPage: 20,
            normalizationContext: ['groups' => ['client:read']]
        ),
        new Get(normalizationContext: ['groups' => ['client:read', 'client:detail']]),
        new Post(denormalizationContext: ['groups' => ['client:write']]),
        new Put(denormalizationContext: ['groups' => ['client:write']]),
        new Patch(denormalizationContext: ['groups' => ['client:write']]),
        new Delete()
    ],
    normalizationContext: ['groups' => ['client:read']],
    denormalizationContext: ['groups' => ['client:write']]
)]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['client:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['client:read', 'client:write'])]
    private ?string $nom = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['client:read', 'client:write'])]
    private ?string $prenom = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'L\'email {{ value }} n\'est pas valide.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['client:read', 'client:write'])]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Assert\Length(
        max: 20,
        maxMessage: 'Le téléphone ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['client:read', 'client:write'])]
    private ?string $telephone = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['client:read', 'client:write'])]
    private ?string $adresse = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    #[Assert\Length(
        max: 10,
        maxMessage: 'Le code postal ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['client:read', 'client:write'])]
    private ?string $codePostal = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(
        max: 100,
        maxMessage: 'La ville ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['client:read', 'client:write'])]
    private ?string $ville = null;

    #[ORM\Column(type: Types::STRING, length: 100, options: ['default' => 'France'])]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Le pays ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['client:read', 'client:write'])]
    private string $pays = 'France';

    #[ORM\Column(type: Types::STRING, length: 14, nullable: true)]
    #[Assert\Length(
        min: 14,
        max: 14,
        exactMessage: 'Le SIRET doit contenir exactement {{ limit }} caractères.'
    )]
    #[Groups(['client:read', 'client:write'])]
    private ?string $siret = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Assert\Length(
        max: 20,
        maxMessage: 'La TVA intracommunautaire ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['client:read', 'client:write'])]
    private ?string $tvaIntracommunautaire = null;

    #[ORM\Column(type: Types::STRING, enumType: ClientStatus::class)]
    #[Assert\NotNull(message: 'Le statut est obligatoire.')]
    #[Groups(['client:write'])]
    private ClientStatus $statut = ClientStatus::PROSPECT;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['client:read'])]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['client:read'])]
    private ?\DateTimeInterface $dateModification = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'Les notes ne peuvent pas dépasser {{ limit }} caractères.'
    )]
    #[Groups(['client:read', 'client:write'])]
    private ?string $notes = null;

    /**
     * @var Collection<int, Devis>
     */
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Devis::class, cascade: ['persist', 'remove'])]
    #[Groups(['client:detail'])]
    private Collection $devis;

    /**
     * @var Collection<int, Facture>
     */
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Facture::class, cascade: ['persist', 'remove'])]
    #[Groups(['client:detail'])]
    private Collection $factures;

    public function __construct()
    {
        $this->devis = new ArrayCollection();
        $this->factures = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): static
    {
        $this->codePostal = $codePostal;
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;
        return $this;
    }

    public function getPays(): string
    {
        return $this->pays;
    }

    public function setPays(string $pays): static
    {
        $this->pays = $pays;
        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): static
    {
        $this->siret = $siret;
        return $this;
    }

    public function getTvaIntracommunautaire(): ?string
    {
        return $this->tvaIntracommunautaire;
    }

    public function setTvaIntracommunautaire(?string $tvaIntracommunautaire): static
    {
        $this->tvaIntracommunautaire = $tvaIntracommunautaire;
        return $this;
    }

    public function getStatut(): ClientStatus
    {
        return $this->statut;
    }

    public function setStatut(ClientStatus $statut): static
    {
        $this->statut = $statut;
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

    public function getDateModification(): ?\DateTimeInterface
    {
        return $this->dateModification;
    }

    public function setDateModification(\DateTimeInterface $dateModification): static
    {
        $this->dateModification = $dateModification;
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

    /**
     * @return Collection<int, Devis>
     */
    public function getDevis(): Collection
    {
        return $this->devis;
    }

    public function addDevi(Devis $devi): static
    {
        if (!$this->devis->contains($devi)) {
            $this->devis->add($devi);
            $devi->setClient($this);
        }
        return $this;
    }

    public function removeDevi(Devis $devi): static
    {
        if ($this->devis->removeElement($devi)) {
            if ($devi->getClient() === $this) {
                $devi->setClient(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Facture>
     */
    public function getFactures(): Collection
    {
        return $this->factures;
    }

    public function addFacture(Facture $facture): static
    {
        if (!$this->factures->contains($facture)) {
            $this->factures->add($facture);
            $facture->setClient($this);
        }
        return $this;
    }

    public function removeFacture(Facture $facture): static
    {
        if ($this->factures->removeElement($facture)) {
            if ($facture->getClient() === $this) {
                $facture->setClient(null);
            }
        }
        return $this;
    }

    /**
     * Retourne le nom complet du client
     */
    public function getNomComplet(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }

    /**
     * Retourne l'adresse complète formatée
     */
    public function getAdresseComplete(): ?string
    {
        if (!$this->adresse) {
            return null;
        }

        $adresse = $this->adresse;
        if ($this->codePostal && $this->ville) {
            $adresse .= ', ' . $this->codePostal . ' ' . $this->ville;
        }
        if ($this->pays && $this->pays !== 'France') {
            $adresse .= ', ' . $this->pays;
        }

        return $adresse;
    }

    /**
     * Retourne le nombre total de devis
     */
    public function getNombreDevis(): int
    {
        return $this->devis->count();
    }

    /**
     * Retourne le nombre total de factures
     */
    public function getNombreFactures(): int
    {
        return $this->factures->count();
    }

    /**
     * Retourne le montant total des factures payées
     */
    public function getMontantTotalFacture(): float
    {
        $total = 0;
        foreach ($this->factures as $facture) {
            // Pour l'instant, on additionne toutes les factures
            // Plus tard, on filtrera par statut PAYEE
            $montantTTC = (float) $facture->getMontantTTC();
            $total += $montantTTC;
        }
        return $total;
    }

    #[ORM\PrePersist]
    public function setDateCreationValue(): void
    {
        $this->dateCreation = new \DateTime();
        $this->dateModification = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setDateModificationValue(): void
    {
        $this->dateModification = new \DateTime();
    }

    public function __toString(): string
    {
        return $this->getNomComplet();
    }

    /**
     * Retourne le statut formaté pour l'affichage (ex: "Prospect")
     */
    #[Groups(['client:read'])]
    public function getStatutLabel(): string
    {
        return $this->statut->getLabel();
    }

    /**
     * Retourne la valeur brute du statut (ex: "prospect")
     */
    #[Groups(['client:read'])]
    public function getStatutValue(): string
    {
        return $this->statut->value;
    }
}
