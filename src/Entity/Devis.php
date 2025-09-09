<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'devis')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['devis:read']],
    denormalizationContext: ['groups' => ['devis:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'numero' => 'partial',
    'client.nom' => 'partial',
    'client.prenom' => 'partial',
    'client.email' => 'partial',
    'statut' => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: ['dateCreation', 'dateValidite', 'montantTTC'])]
#[ApiFilter(DateFilter::class, properties: ['dateCreation', 'dateValidite', 'dateAcceptation'])]
#[ApiFilter(RangeFilter::class, properties: ['montantHT', 'montantTTC'])]
class Devis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['devis:read', 'devis:write'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Le numéro du devis est obligatoire')]
    #[Assert\Length(max: 50, maxMessage: 'Le numéro ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['devis:read', 'devis:write'])]
    private ?string $numero = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'devis')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le client est obligatoire')]
    #[Groups(['devis:read', 'devis:write'])]
    private ?Client $client = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['devis:read', 'devis:write'])]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['devis:read', 'devis:write'])]
    private ?\DateTimeInterface $dateValidite = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: DevisStatus::class)]
    #[Assert\NotNull(message: 'Le statut est obligatoire')]
    #[Groups(['devis:write'])]
    private ?DevisStatus $statut = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Assert\NotBlank(message: 'Le montant HT est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le montant HT doit être un nombre')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le montant HT ne peut pas être négatif')]
    #[Groups(['devis:read', 'devis:write'])]
    private string $montantHT = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => 20.00])]
    #[Assert\NotBlank(message: 'Le taux de TVA est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le taux de TVA doit être un nombre')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le taux de TVA ne peut pas être négatif')]
    #[Assert\LessThanOrEqual(value: 100, message: 'Le taux de TVA ne peut pas dépasser 100%')]
    #[Groups(['devis:read', 'devis:write'])]
    private string $tauxTVA = '20.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Assert\NotBlank(message: 'Le montant TTC est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le montant TTC doit être un nombre')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le montant TTC ne peut pas être négatif')]
    #[Groups(['devis:read', 'devis:write'])]
    private string $montantTTC = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => 30.00])]
    #[Assert\NotBlank(message: 'Le pourcentage d\'acompte est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le pourcentage d\'acompte doit être un nombre')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le pourcentage d\'acompte ne peut pas être négatif')]
    #[Assert\LessThanOrEqual(value: 100, message: 'Le pourcentage d\'acompte ne peut pas dépasser 100%')]
    #[Groups(['devis:read', 'devis:write'])]
    private string $acomptePourcentage = '30.00';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['devis:read', 'devis:write'])]
    private ?string $conditionsPaiement = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['devis:read', 'devis:write'])]
    private ?string $delaiLivraison = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['devis:read', 'devis:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['devis:read', 'devis:write'])]
    private ?\DateTimeInterface $dateAcceptation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['devis:read', 'devis:write'])]
    private ?string $signatureClient = null;

    // ===== NOUVELLES MENTIONS OBLIGATOIRES (2026-2027) =====

    #[ORM\Column(type: Types::STRING, length: 9, nullable: true)]
    #[Assert\Length(exactly: 9, exactMessage: 'Le SIREN doit contenir exactement 9 chiffres')]
    #[Assert\Regex(pattern: '/^[0-9]{9}$/', message: 'Le SIREN ne peut contenir que des chiffres')]
    #[Groups(['devis:read', 'devis:write'])]
    private ?string $sirenClient = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['devis:read', 'devis:write'])]
    private ?string $adresseLivraison = null;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'services'])]
    #[Assert\NotBlank(message: 'Le type d\'opérations est obligatoire')]
    #[Assert\Choice(choices: ['biens', 'services', 'mixte'], message: 'Le type d\'opérations doit être \'biens\', \'services\' ou \'mixte\'')]
    #[Groups(['devis:read', 'devis:write'])]
    private string $typeOperations = 'services';

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['devis:read', 'devis:write'])]
    private bool $paiementTvaSurDebits = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['devis:read', 'devis:write'])]
    private ?\DateTimeInterface $dateModification = null;


    /**
     * Facture générée à partir de ce devis
     */
    #[ORM\OneToOne(targetEntity: Facture::class, mappedBy: 'devis')]
    #[Groups(['devis:read'])]
    private ?Facture $facture = null;

    /**
     * Tarifs associés à ce devis (plusieurs tarifs possibles)
     */
    #[ORM\ManyToMany(targetEntity: Tarif::class)]
    #[ORM\JoinTable(name: 'devis_tarifs')]
    #[Groups(['devis:read', 'devis:write'])]
    private \Doctrine\Common\Collections\Collection $tarifs;

    public function __construct()
    {
        $this->tarifs = new \Doctrine\Common\Collections\ArrayCollection();
        $this->statut = DevisStatus::BROUILLON;
        $this->dateCreation = new \DateTime();
        $this->dateModification = new \DateTime();
    }

    // ===== GETTERS ET SETTERS =====

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

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;
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

    public function getDateValidite(): ?\DateTimeInterface
    {
        return $this->dateValidite;
    }

    public function setDateValidite(?\DateTimeInterface $dateValidite): self
    {
        $this->dateValidite = $dateValidite;
        return $this;
    }

    public function getStatut(): ?DevisStatus
    {
        return $this->statut;
    }

    public function setStatut(?DevisStatus $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    public function getMontantHT(): string
    {
        return $this->montantHT;
    }

    public function setMontantHT(string $montantHT): self
    {
        $this->montantHT = $montantHT;
        return $this;
    }

    public function getTauxTVA(): string
    {
        return $this->tauxTVA;
    }


    public function getMontantTTC(): string
    {
        return $this->montantTTC;
    }

    public function setMontantTTC(string $montantTTC): self
    {
        $this->montantTTC = $montantTTC;
        return $this;
    }

    public function getAcomptePourcentage(): string
    {
        return $this->acomptePourcentage;
    }

    public function setAcomptePourcentage(string $acomptePourcentage): self
    {
        $this->acomptePourcentage = $acomptePourcentage;
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

    public function getDelaiLivraison(): ?string
    {
        return $this->delaiLivraison;
    }

    public function setDelaiLivraison(?string $delaiLivraison): self
    {
        $this->delaiLivraison = $delaiLivraison;
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

    public function getDateAcceptation(): ?\DateTimeInterface
    {
        return $this->dateAcceptation;
    }

    public function setDateAcceptation(?\DateTimeInterface $dateAcceptation): self
    {
        $this->dateAcceptation = $dateAcceptation;
        return $this;
    }

    public function getSignatureClient(): ?string
    {
        return $this->signatureClient;
    }

    public function setSignatureClient(?string $signatureClient): self
    {
        $this->signatureClient = $signatureClient;
        return $this;
    }

    // ===== NOUVELLES MENTIONS OBLIGATOIRES =====

    public function getSirenClient(): ?string
    {
        return $this->sirenClient;
    }

    public function setSirenClient(?string $sirenClient): self
    {
        $this->sirenClient = $sirenClient;
        return $this;
    }

    public function getAdresseLivraison(): ?string
    {
        return $this->adresseLivraison;
    }

    public function setAdresseLivraison(?string $adresseLivraison): self
    {
        $this->adresseLivraison = $adresseLivraison;
        return $this;
    }

    public function getTypeOperations(): string
    {
        return $this->typeOperations;
    }

    public function setTypeOperations(string $typeOperations): self
    {
        $this->typeOperations = $typeOperations;
        return $this;
    }

    public function getPaiementTvaSurDebits(): bool
    {
        return $this->paiementTvaSurDebits;
    }

    public function setPaiementTvaSurDebits(bool $paiementTvaSurDebits): self
    {
        $this->paiementTvaSurDebits = $paiementTvaSurDebits;
        return $this;
    }

    public function getDateModification(): ?\DateTimeInterface
    {
        return $this->dateModification;
    }

    public function setDateModification(\DateTimeInterface $dateModification): self
    {
        $this->dateModification = $dateModification;
        return $this;
    }


    public function getFacture(): ?Facture
    {
        return $this->facture;
    }

    public function setFacture(?Facture $facture): self
    {
        $this->facture = $facture;
        return $this;
    }

    public function getTarifs(): \Doctrine\Common\Collections\Collection
    {
        return $this->tarifs;
    }

    public function addTarif(Tarif $tarif): self
    {
        if (!$this->tarifs->contains($tarif)) {
            $this->tarifs[] = $tarif;
            $this->calculerMontantsDepuisTarifs();
        }
        return $this;
    }

    public function removeTarif(Tarif $tarif): self
    {
        if ($this->tarifs->removeElement($tarif)) {
            $this->calculerMontantsDepuisTarifs();
        }
        return $this;
    }

    public function setTarifs(\Doctrine\Common\Collections\Collection $tarifs): self
    {
        $this->tarifs = $tarifs;
        $this->calculerMontantsDepuisTarifs();
        return $this;
    }

    /**
     * Calcule automatiquement les montants HT et TTC depuis les tarifs sélectionnés
     */
    public function calculerMontantsDepuisTarifs(): void
    {
        if ($this->tarifs->isEmpty()) {
            return;
        }

        $totalHT = 0;

        // Somme de tous les tarifs sélectionnés (convertir centimes en euros)
        foreach ($this->tarifs as $tarif) {
            $prixEnCentimes = (float) $tarif->getPrix();
            $prixEnEuros = $prixEnCentimes / 100;
            $totalHT += $prixEnEuros;
        }

        $tauxTVA = (float) $this->tauxTVA;

        // Montant HT = somme des tarifs (en euros)
        $this->montantHT = number_format($totalHT, 2, '.', '');

        // Montant TTC = HT + TVA
        if ($tauxTVA > 0) {
            $montantTVA = $totalHT * ($tauxTVA / 100);
            $montantTTC = $totalHT + $montantTVA;
        } else {
            // Micro-entrepreneur : HT = TTC
            $montantTTC = $totalHT;
        }

        $this->montantTTC = number_format($montantTTC, 2, '.', '');
    }

    /**
     * Recalcule les montants quand le taux de TVA change
     */
    public function setTauxTVA(string $tauxTVA): self
    {
        $this->tauxTVA = $tauxTVA;

        // Recalculer les montants si des tarifs sont déjà sélectionnés
        if (!$this->tarifs->isEmpty()) {
            $this->calculerMontantsDepuisTarifs();
        }

        return $this;
    }

    // ===== MÉTHODES UTILITAIRES =====

    /**
     * Retourne le libellé du statut pour l'API
     */
    #[Groups(['devis:read'])]
    public function getStatutLabel(): string
    {
        return $this->statut?->getLabel() ?? 'Inconnu';
    }

    /**
     * Retourne la valeur du statut pour l'API
     */
    #[Groups(['devis:read'])]
    public function getStatutValue(): string
    {
        return $this->statut?->value ?? 'inconnu';
    }

    /**
     * Calcule le montant de l'acompte
     */
    #[Groups(['devis:read'])]
    public function getMontantAcompte(): string
    {
        $montantTTC = (float) $this->montantTTC;
        $acomptePourcentage = (float) $this->acomptePourcentage;

        // EasyAdmin MoneyField stocke en centimes, donc on divise par 100
        $montantTTCEnEuros = $montantTTC / 100;
        $montantAcompte = $montantTTCEnEuros * ($acomptePourcentage / 100);

        return number_format(round($montantAcompte, 2), 2, '.', '');
    }

    /**
     * Calcule le montant de la TVA
     */
    #[Groups(['devis:read'])]
    public function getMontantTVA(): string
    {
        $montantTTC = (float) $this->montantTTC;
        $montantHT = (float) $this->montantHT;

        // EasyAdmin MoneyField stocke en centimes, donc on divise par 100
        $montantTTCEnEuros = $montantTTC / 100;
        $montantHTEnEuros = $montantHT / 100;

        return number_format(round($montantTTCEnEuros - $montantHTEnEuros, 2), 2, '.', '');
    }

    /**
     * Retourne l'adresse complète du client
     */
    #[Groups(['devis:read'])]
    public function getAdresseClientComplete(): ?string
    {
        return $this->client?->getAdresseComplete();
    }

    /**
     * Vérifie si le devis est expiré
     */
    public function isExpired(): bool
    {
        if (!$this->dateValidite) {
            return false;
        }
        return $this->dateValidite < new \DateTime();
    }

    /**
     * Vérifie si le devis peut être modifié
     */
    public function canBeModified(): bool
    {
        return $this->statut && !$this->statut->isFinal();
    }

    /**
     * Vérifie si le devis peut être envoyé
     */
    public function canBeSent(): bool
    {
        return $this->statut && $this->statut->canBeSent();
    }

    /**
     * Vérifie si le devis peut être accepté
     */
    public function canBeAccepted(): bool
    {
        return $this->statut && $this->statut->canBeAccepted();
    }

    /**
     * Retourne le type d'opérations en français
     */
    #[Groups(['devis:read'])]
    public function getTypeOperationsLabel(): string
    {
        return match ($this->typeOperations) {
            'biens' => 'Livraisons de biens uniquement',
            'services' => 'Prestations de services uniquement',
            'mixte' => 'Biens et services',
            default => 'Non défini'
        };
    }

    /**
     * Vérifie si c'est un micro-entrepreneur (TVA à 0%)
     */
    public function isMicroEntrepreneur(): bool
    {
        return (float) $this->tauxTVA === 0.0;
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
