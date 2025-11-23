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
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use App\Entity\Amendment;

#[ORM\Entity]
#[ORM\Table(name: 'quotes')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(
            security: "is_granted('ROLE_USER')",
            securityMessage: "Seuls les utilisateurs authentifiés peuvent créer des devis via l'API."
        ),
        new Put(
            security: "is_granted('QUOTE_EDIT', object)",
            securityMessage: "Vous n'avez pas la permission de modifier ce devis."
        ),
        new Patch(
            security: "is_granted('QUOTE_EDIT', object)",
            securityMessage: "Vous n'avez pas la permission de modifier ce devis."
        ),
        new Delete(
            security: "false",
            securityMessage: "Les devis ne peuvent pas être supprimés. Utilisez l'annulation."
        ),
        // Opérations custom pour les transitions d'état
        new Post(
            uriTemplate: '/quotes/{id}/send',
            controller: \App\Controller\Api\QuoteSendController::class,
            security: "is_granted('QUOTE_SEND', object)",
            securityMessage: "Vous n'avez pas la permission d'envoyer ce devis.",
            read: false,
            name: 'quote_send'
        ),
        new Post(
            uriTemplate: '/quotes/{id}/accept',
            controller: \App\Controller\Api\QuoteAcceptController::class,
            security: "is_granted('QUOTE_ACCEPT', object)",
            securityMessage: "Vous n'avez pas la permission d'accepter ce devis.",
            read: false,
            name: 'quote_accept'
        ),
        new Post(
            uriTemplate: '/quotes/{id}/sign',
            controller: \App\Controller\Api\QuoteSignController::class,
            security: "is_granted('QUOTE_SIGN', object)",
            securityMessage: "Vous n'avez pas la permission de signer ce devis.",
            read: false,
            name: 'quote_sign'
        ),
        new Post(
            uriTemplate: '/quotes/{id}/cancel',
            controller: \App\Controller\Api\QuoteCancelController::class,
            security: "is_granted('QUOTE_CANCEL', object)",
            securityMessage: "Vous n'avez pas la permission d'annuler ce devis.",
            read: false,
            name: 'quote_cancel'
        ),
        new Post(
            uriTemplate: '/quotes/{id}/refuse',
            controller: \App\Controller\Api\QuoteRefuseController::class,
            security: "is_granted('QUOTE_REFUSE', object)",
            securityMessage: "Vous n'avez pas la permission de refuser ce devis.",
            read: false,
            name: 'quote_refuse'
        ),
        new Post(
            uriTemplate: '/quotes/{id}/generate-invoice',
            controller: \App\Controller\Api\QuoteGenerateInvoiceController::class,
            security: "is_granted('QUOTE_GENERATE_INVOICE', object)",
            securityMessage: "Vous n'avez pas la permission de générer une facture depuis ce devis.",
            read: false,
            name: 'quote_generate_invoice'
        )
    ],
    normalizationContext: ['groups' => ['quote:read']],
    denormalizationContext: ['groups' => ['quote:write']]
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
class Quote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['quote:read', 'quote:write'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'Le numéro ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['quote:read', 'quote:write'])]
    private ?string $numero = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'quotes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le client est obligatoire')]
    #[Groups(['quote:read', 'quote:write'])]
    private ?Client $client = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['quote:read', 'quote:write'])]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['quote:read', 'quote:write'])]
    private ?\DateTimeInterface $dateValidite = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: QuoteStatus::class)]
    #[Assert\NotNull(message: 'Le statut est obligatoire')]
    #[Groups(['quote:write'])]
    private ?QuoteStatus $statut = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Assert\NotBlank(message: 'Le montant HT est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le montant HT doit être un nombre')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le montant HT ne peut pas être négatif')]
    #[Groups(['quote:read', 'quote:write'])]
    private string $montantHT = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => 0.00])]
    #[Assert\NotBlank(message: 'Le taux de TVA est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le taux de TVA doit être un nombre')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le taux de TVA ne peut pas être négatif')]
    #[Assert\LessThanOrEqual(value: 100, message: 'Le taux de TVA ne peut pas dépasser 100%')]
    #[Groups(['quote:read', 'quote:write'])]
    private string $tauxTVA = '0.00';

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['quote:read', 'quote:write'])]
    private bool $usePerLineTva = false;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Assert\NotBlank(message: 'Le montant TTC est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le montant TTC doit être un nombre')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le montant TTC ne peut pas être négatif')]
    #[Groups(['quote:read', 'quote:write'])]
    private string $montantTTC = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => 30.00])]
    #[Assert\NotBlank(message: 'Le pourcentage d\'acompte est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le pourcentage d\'acompte doit être un nombre')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le pourcentage d\'acompte ne peut pas être négatif')]
    #[Assert\LessThanOrEqual(value: 100, message: 'Le pourcentage d\'acompte ne peut pas dépasser 100%')]
    #[Groups(['quote:read', 'quote:write'])]
    private string $acomptePourcentage = '30.00';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['quote:read', 'quote:write'])]
    private ?string $conditionsPaiement = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['quote:read', 'quote:write'])]
    private ?string $delaiLivraison = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['quote:read', 'quote:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['quote:read', 'quote:write'])]
    private ?\DateTimeInterface $dateAcceptation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['quote:read', 'quote:write'])]
    private ?string $signatureClient = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['quote:read', 'quote:write'])]
    private ?\DateTimeInterface $dateSignature = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['quote:read', 'quote:write'])]
    private ?\DateTimeInterface $dateEnvoi = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['quote:read'])]
    private int $sentCount = 0;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Groups(['quote:read'])]
    private ?string $deliveryChannel = null; // 'email', 'pdp', 'both'

    // ===== NOUVELLES MENTIONS OBLIGATOIRES (2026-2027) =====

    #[ORM\Column(type: Types::STRING, length: 9, nullable: true)]
    #[Assert\Length(exactly: 9, exactMessage: 'Le SIREN doit contenir exactement 9 chiffres')]
    #[Assert\Regex(pattern: '/^[0-9]{9}$/', message: 'Le SIREN ne peut contenir que des chiffres')]
    #[Groups(['quote:read', 'quote:write'])]
    private ?string $sirenClient = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['quote:read', 'quote:write'])]
    private ?string $adresseLivraison = null;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'services'])]
    #[Assert\NotBlank(message: 'Le type d\'opérations est obligatoire')]
    #[Assert\Choice(choices: ['biens', 'services', 'mixte'], message: 'Le type d\'opérations doit être \'biens\', \'services\' ou \'mixte\'')]
    #[Groups(['quote:read', 'quote:write'])]
    private string $typeOperations = 'services';

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['quote:read', 'quote:write'])]
    private bool $paiementTvaSurDebits = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['quote:read', 'quote:write'])]
    private ?\DateTimeInterface $dateModification = null;

    #[ORM\Column(length: 36)]
    #[Assert\NotBlank(message: 'Le company_id est obligatoire')]
    #[Groups(['quote:read', 'quote:write'])]
    private ?string $companyId = null;

    /**
     * Nom du fichier PDF généré
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['quote:read'])]
    private ?string $pdfFilename = null;

    /**
     * Hash SHA256 du PDF pour archivage légal (10 ans)
     */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    #[Groups(['quote:read'])]
    private ?string $pdfHash = null;

    /**
     * Invoice générée à partir de ce quote
     */
    #[ORM\OneToOne(targetEntity: Invoice::class, mappedBy: 'quote')]
    #[Groups(['quote:read'])]
    private ?Invoice $invoice = null;

    /**
     * @var Collection<int, Amendment>
     */
    #[ORM\OneToMany(targetEntity: Amendment::class, mappedBy: 'quote')]
    #[Groups(['quote:read'])]
    private Collection $amendments;

    /**
     * @var Collection<int, QuoteLine>
     */
    #[ORM\OneToMany(targetEntity: QuoteLine::class, mappedBy: 'quote', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['quote:read', 'quote:write'])]
    private Collection $lines;

    public function __construct()
    {
        $this->statut = QuoteStatus::DRAFT;
        $this->dateCreation = new \DateTime();
        $this->dateModification = new \DateTime();
        $this->lines = new ArrayCollection();
        $this->amendments = new ArrayCollection();
        // Taux de TVA par défaut à 0.00 (sera remplacé par CompanySettings si disponible)
        $this->tauxTVA = '0.00';
    }

    // ===== GETTERS ET SETTERS =====

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isUsePerLineTva(): bool
    {
        return $this->usePerLineTva;
    }

    public function setUsePerLineTva(bool $usePerLineTva): self
    {
        $this->usePerLineTva = $usePerLineTva;
        return $this;
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

    public function getStatut(): ?QuoteStatus
    {
        return $this->statut;
    }

    public function setStatut(?QuoteStatus $statut): self
    {
        // Si passage à SIGNED, valider que c'est possible
        // Mais seulement si l'entité a déjà un ID (créée en base) ou si les lignes sont déjà associées
        // Cela évite l'erreur lors de la création via formulaire où les lignes ne sont pas encore associées
        if ($statut === QuoteStatus::SIGNED && $this->statut !== QuoteStatus::SIGNED) {
            // Vérifier si les lignes sont déjà associées (via la collection)
            // Si l'entité n'a pas d'ID, on est en création et les lignes seront associées plus tard
            if ($this->id !== null || !$this->lines->isEmpty()) {
                $this->validateCanBeSigned();
            }
        }

        $this->statut = $statut;
        return $this;
    }

    /**
     * Valide que le devis peut être signé
     * 
     * @throws \RuntimeException si le devis ne peut pas être signé
     */
    public function validateCanBeSigned(): void
    {
        // Vérifier qu'au moins une ligne est présente
        if ($this->lines->isEmpty()) {
            throw new \RuntimeException('Un devis ne peut pas être signé sans ligne.');
        }

        // Vérifier que le montant HT est positif
        if ((float) $this->montantHT <= 0) {
            throw new \RuntimeException('Un devis ne peut pas être signé avec un montant HT négatif ou nul.');
        }

        // Vérifier que le montant TTC est positif
        if ((float) $this->montantTTC <= 0) {
            throw new \RuntimeException('Un devis ne peut pas être signé avec un montant TTC négatif ou nul.');
        }

        // Vérifier que le devis n'est pas annulé
        if ($this->statut === QuoteStatus::CANCELLED) {
            throw new \RuntimeException('Un devis annulé ne peut pas être signé.');
        }

        // Vérifier que le devis n'est pas expiré
        if ($this->isExpired()) {
            throw new \RuntimeException('Un devis expiré ne peut pas être signé.');
        }
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

    public function getDateSignature(): ?\DateTimeInterface
    {
        return $this->dateSignature;
    }

    public function setDateSignature(?\DateTimeInterface $dateSignature): self
    {
        $this->dateSignature = $dateSignature;
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

    public function getSentCount(): int
    {
        return $this->sentCount;
    }

    public function setSentCount(int $sentCount): self
    {
        $this->sentCount = $sentCount;
        return $this;
    }

    public function incrementSentCount(): self
    {
        $this->sentCount++;
        return $this;
    }

    public function getDeliveryChannel(): ?string
    {
        return $this->deliveryChannel;
    }

    public function setDeliveryChannel(?string $deliveryChannel): self
    {
        $this->deliveryChannel = $deliveryChannel;
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

    public function getCompanyId(): ?string
    {
        return $this->companyId;
    }

    public function setCompanyId(string $companyId): self
    {
        $this->companyId = $companyId;
        return $this;
    }

    public function getPdfFilename(): ?string
    {
        return $this->pdfFilename;
    }

    public function setPdfFilename(?string $pdfFilename): static
    {
        $this->pdfFilename = $pdfFilename;
        return $this;
    }

    public function getPdfHash(): ?string
    {
        return $this->pdfHash;
    }

    public function setPdfHash(?string $pdfHash): static
    {
        $this->pdfHash = $pdfHash;
        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): self
    {
        $this->invoice = $invoice;
        return $this;
    }

    /**
     * Recalcule les montants quand le taux de TVA change
     */
    public function setTauxTVA(string $tauxTVA): self
    {
        $this->tauxTVA = $tauxTVA;
        return $this;
    }

    /**
     * Recalcule les montants HT/TTC depuis les lignes
     * - Si usePerLineTva = true: somme TTC par ligne
     * - Sinon: applique le taux global du devis sur le total HT
     * Les montants sont stockés en euros (DECIMAL, string avec 2 décimales)
     */
    public function recalculateTotalsFromLines(): void
    {
        if ($this->lines->isEmpty()) {
            $this->montantHT = '0.00';
            $this->montantTTC = '0.00';
            return;
        }

        $totalHtEuros = 0.0;
        foreach ($this->lines as $line) {
            $totalHtEuros += (float) ($line->getTotalHt() ?? 0);
        }

        $this->montantHT = number_format($totalHtEuros, 2, '.', '');

        if ($this->usePerLineTva) {
            $totalTtcEuros = 0.0;
            foreach ($this->lines as $line) {
                $totalTtcEuros += (float) $line->getTotalTtc();
            }
            $this->montantTTC = number_format($totalTtcEuros, 2, '.', '');
            return;
        }

        // TVA globale appliquée au total HT
        $tauxTVA = (float) $this->tauxTVA;
        if ($tauxTVA > 0) {
            $tvaAmount = $totalHtEuros * ($tauxTVA / 100);
            $this->montantTTC = number_format($totalHtEuros + $tvaAmount, 2, '.', '');
        } else {
            $this->montantTTC = number_format($totalHtEuros, 2, '.', '');
        }
    }

    // ===== MÉTHODES UTILITAIRES =====

    /**
     * Retourne le libellé du statut pour l'API
     */
    #[Groups(['quote:read'])]
    public function getStatutLabel(): string
    {
        return $this->statut?->getLabel() ?? 'Inconnu';
    }

    /**
     * Retourne la valeur du statut pour l'API
     */
    #[Groups(['quote:read'])]
    public function getStatutValue(): string
    {
        return $this->statut?->value ?? 'draft';
    }

    /**
     * Calcule le montant de l'acompte
     */
    #[Groups(['quote:read'])]
    public function getMontantAcompte(): string
    {
        $montantTTC = (float) $this->montantTTC; // Montant déjà en euros (DECIMAL)
        $acomptePourcentage = (float) $this->acomptePourcentage;

        $montantAcompte = $montantTTC * ($acomptePourcentage / 100);

        return number_format(round($montantAcompte, 2), 2, '.', '');
    }

    /**
     * Calcule le montant de la TVA
     */
    #[Groups(['quote:read'])]
    public function getMontantTVA(): string
    {
        $montantTTC = (float) $this->montantTTC; // Montant déjà en euros (DECIMAL)
        $montantHT = (float) $this->montantHT; // Montant déjà en euros (DECIMAL)

        return number_format(round($montantTTC - $montantHT, 2), 2, '.', '');
    }

    /**
     * Retourne l'adresse complète du client
     */
    #[Groups(['quote:read'])]
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
    #[Groups(['quote:read'])]
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

    // ===== MÉTHODES D'AFFICHAGE =====

    /**
     * Retourne une représentation string de l'entité
     */
    public function __toString(): string
    {
        $client = $this->getClient() ? $this->getClient()->getNomComplet() : 'Client inconnu';
        return sprintf('%s - %s (%s)', $this->numero ?? 'Quote #' . $this->id, $client, $this->getMontantTTCFormate());
    }

    /**
     * Retourne le montant HT formaté pour l'affichage
     */
    public function getMontantHTFormate(): string
    {
        $montant = (float) $this->montantHT; // Montant déjà en euros (DECIMAL)
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Retourne le montant TTC formaté pour l'affichage
     */
    public function getMontantTTCFormate(): string
    {
        $montant = (float) $this->montantTTC; // Montant déjà en euros (DECIMAL)
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Calcule le total corrigé du devis en tenant compte des avenants modifiables
     * totalCorrected = totalOriginal + sum(all deltas from modifiable amendments)
     * 
     * CONFORMITÉ LÉGALE : 
     * - Les avenants en brouillon (DRAFT) ou envoyés (SENT) peuvent encore être modifiés, donc on les inclut dans le calcul
     * - Les avenants signés (SIGNED) sont définitifs et doivent être inclus
     * - Les avenants annulés (CANCELLED) ne doivent pas être inclus
     */
    public function getTotalCorrected(): string
    {
        $totalOriginal = (float) $this->montantTTC;
        $totalDeltasTtc = 0.0;

        // Somme des deltas TTC de tous les avenants modifiables (DRAFT, SENT) ou signés (SIGNED)
        // Les avenants annulés (CANCELLED) ne sont pas pris en compte
        // IMPORTANT : Utiliser getDeltaTtc() car le delta est en HT mais on doit l'additionner au montant TTC
        foreach ($this->amendments as $amendment) {
            $status = $amendment->getStatutEnum();
            // Inclure tous les avenants sauf ceux annulés (CANCELLED)
            // Cela inclut DRAFT, SENT, et SIGNED
            if ($status && $status !== \App\Entity\AmendmentStatus::CANCELLED) {
                foreach ($amendment->getLines() as $line) {
                    $deltaTtc = (float) $line->getDeltaTtc();
                    $totalDeltasTtc += $deltaTtc;
                }
            }
        }

        $totalCorrected = $totalOriginal + $totalDeltasTtc;
        // S'assurer que le résultat est arrondi correctement
        $totalCorrected = round($totalCorrected, 2);
        return number_format($totalCorrected, 2, '.', '');
    }

    /**
     * Retourne le total corrigé formaté pour l'affichage
     */
    public function getTotalCorrectedFormatted(): string
    {
        $montant = (float) $this->getTotalCorrected();
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Retourne le montant TVA formaté pour l'affichage
     */
    public function getMontantTVAFormate(): string
    {
        // getMontantTVA() retourne déjà en euros, pas besoin de diviser par 100
        $montant = (float) $this->getMontantTVA();
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Retourne le montant acompte formaté pour l'affichage
     */
    public function getMontantAcompteFormate(): string
    {
        $montant = (float) $this->getMontantAcompte();
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Calcule le montant de l'acompte à partir du total corrigé (incluant les avenants signés)
     * Utilise le total corrigé brut (sans arrondi intermédiaire) pour éviter les erreurs d'arrondi
     */
    public function getMontantAcompteCorrige(): string
    {
        // Calculer le total corrigé sans arrondi intermédiaire (même logique que getTotalCorrected)
        $totalOriginal = (float) $this->montantTTC;
        $totalDeltasTtc = 0.0;

        foreach ($this->amendments as $amendment) {
            $status = $amendment->getStatutEnum();
            if ($status && $status !== \App\Entity\AmendmentStatus::CANCELLED) {
                foreach ($amendment->getLines() as $line) {
                    // Utiliser getDeltaTtc() car le delta est en HT mais on doit l'additionner au montant TTC
                    $totalDeltasTtc += (float) $line->getDeltaTtc();
                }
            }
        }

        $totalCorrige = $totalOriginal + $totalDeltasTtc;
        // S'assurer que le total corrigé est arrondi correctement
        $totalCorrige = round($totalCorrige, 2);
        $acomptePourcentage = (float) $this->acomptePourcentage;

        // Calculer l'acompte avec arrondi à 2 décimales
        $montantAcompte = round($totalCorrige * ($acomptePourcentage / 100), 2);

        return number_format($montantAcompte, 2, '.', '');
    }

    /**
     * Retourne le montant acompte corrigé formaté pour l'affichage
     */
    public function getMontantAcompteCorrigeFormate(): string
    {
        $montant = (float) $this->getMontantAcompteCorrige();
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Calcule le solde restant après acompte corrigé (incluant les avenants)
     * Utilise les mêmes calculs que getMontantAcompteCorrige pour garantir la cohérence
     */
    public function getSoldeRestantCorrige(): string
    {
        // Calculer le total corrigé sans arrondi intermédiaire (même logique que getMontantAcompteCorrige)
        $totalOriginal = (float) $this->montantTTC;
        $totalDeltasTtc = 0.0;

        foreach ($this->amendments as $amendment) {
            $status = $amendment->getStatutEnum();
            if ($status && $status !== \App\Entity\AmendmentStatus::CANCELLED) {
                foreach ($amendment->getLines() as $line) {
                    // Utiliser getDeltaTtc() car le delta est en HT mais on doit l'additionner au montant TTC
                    $totalDeltasTtc += (float) $line->getDeltaTtc();
                }
            }
        }

        $totalCorrige = $totalOriginal + $totalDeltasTtc;
        // S'assurer que le total corrigé est arrondi correctement
        $totalCorrige = round($totalCorrige, 2);
        $acomptePourcentage = (float) $this->acomptePourcentage;

        // Calculer l'acompte avec arrondi
        $montantAcompteCorrige = round($totalCorrige * ($acomptePourcentage / 100), 2);

        // Calculer le solde restant avec arrondi
        $soldeRestant = round($totalCorrige - $montantAcompteCorrige, 2);

        return number_format($soldeRestant, 2, '.', '');
    }

    /**
     * Retourne le solde restant corrigé formaté pour l'affichage
     */
    public function getSoldeRestantCorrigeFormate(): string
    {
        $montant = (float) $this->getSoldeRestantCorrige();
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Calcule le solde restant après acompte
     */
    public function getSoldeRestant(): string
    {
        $montantTTC = (float) $this->montantTTC; // Montant déjà en euros (DECIMAL)
        $montantAcompte = (float) $this->getMontantAcompte();

        $soldeRestant = $montantTTC - $montantAcompte;

        return number_format(round($soldeRestant, 2), 2, '.', '');
    }

    /**
     * Retourne le solde restant formaté pour l'affichage
     */
    public function getSoldeRestantFormate(): string
    {
        $montant = (float) $this->getSoldeRestant();
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Retourne le détail des taux de TVA utilisés dans les lignes (si usePerLineTva = true)
     * Retourne un tableau associatif [taux => montant_HT_à_ce_taux]
     * Les montants sont stockés en euros (float)
     */
    public function getTvaRatesDetail(): array
    {
        if (!$this->usePerLineTva || $this->lines->isEmpty()) {
            return [];
        }

        $detail = [];
        foreach ($this->lines as $line) {
            $taux = $line->getTvaRate() ?? $this->tauxTVA;
            $tauxKey = (string) $taux;

            if (!isset($detail[$tauxKey])) {
                $detail[$tauxKey] = [
                    'rate' => $taux,
                    'ht' => 0.0,
                    'tva' => 0.0,
                ];
            }

            // getTotalHt() retourne déjà en euros (string comme "20.00")
            $lineHt = (float) ($line->getTotalHt() ?? 0);
            $detail[$tauxKey]['ht'] += $lineHt;

            // Calculer la TVA de cette ligne en euros
            if ($taux && (float) $taux > 0) {
                $tvaAmount = $lineHt * ((float) $taux / 100);
                $detail[$tauxKey]['tva'] += $tvaAmount;
            }
        }

        return $detail;
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

    /**
     * @return Collection<int, QuoteLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(QuoteLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setQuote($this);
        }

        return $this;
    }

    public function removeLine(QuoteLine $line): static
    {
        if ($this->lines->removeElement($line)) {
            // set the owning side to null (unless already changed)
            if ($line->getQuote() === $this) {
                $line->setQuote(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Amendment>
     */
    public function getAmendments(): Collection
    {
        return $this->amendments;
    }

    public function addAmendment(Amendment $amendment): static
    {
        if (!$this->amendments->contains($amendment)) {
            $this->amendments->add($amendment);
            $amendment->setQuote($this);
        }

        return $this;
    }

    public function removeAmendment(Amendment $amendment): static
    {
        if ($this->amendments->removeElement($amendment)) {
            // set the owning side to null (unless already changed)
            if ($amendment->getQuote() === $this) {
                $amendment->setQuote(null);
            }
        }

        return $this;
    }
}
