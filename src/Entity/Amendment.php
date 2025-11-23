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
use App\Entity\QuoteStatus;

/**
 * Entité Amendment pour gérer les modifications des quotes signés
 * 
 * CONTRAINTE LÉGALE : 
 * - Un amendment ne peut être créé que pour un quote SIGNED
 * - Un avenant signé devient immuable
 * - Le montant du devis est recalculé automatiquement : quote.total += amendment.total
 * - Archivage 10 ans obligatoire
 */
#[ORM\Entity(repositoryClass: \App\Repository\AmendmentRepository::class)]
#[ORM\Table(name: 'amendments')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(
            security: "is_granted('ROLE_USER') && object.getQuote() !== null && object.getQuote().getStatut() !== null && object.getQuote().getStatut().value === 'signed'",
            securityMessage: "Seuls les utilisateurs authentifiés peuvent créer des avenants via l'API. L'avenant doit être associé à un devis signé."
        ),
        new Put(
            security: "object.getStatutEnum() !== null && object.getStatutEnum().isModifiable()",
            securityMessage: "Seuls les avenants en brouillon peuvent être modifiés via l'API."
        ),
        new Delete(
            security: "false",
            securityMessage: "Les avenants ne peuvent pas être supprimés. Utilisez l'annulation."
        ),
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

    #[ORM\Column(type: Types::STRING, length: 50, unique: true, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'Le numéro ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['amendment:read', 'amendment:write'])]
    private ?string $numero = null;

    // ===== RELATION OBLIGATOIRE AVEC UN DEVIS SIGNED =====
    #[ORM\ManyToOne(targetEntity: Quote::class)]
    #[ORM\JoinColumn(name: 'quote_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['amendment:read', 'amendment:write'])]
    #[Assert\NotNull(message: "Un avenant doit être lié à un devis")]
    private ?Quote $quote = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['amendment:read', 'amendment:write'])]
    #[Assert\NotBlank(message: "Le motif est obligatoire")]
    private ?string $motif = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['amendment:read', 'amendment:write'])]
    #[Assert\NotBlank(message: "La description des modifications est obligatoire")]
    private ?string $modifications = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['amendment:read', 'amendment:write'])]
    private ?string $justification = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['amendment:read'])]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['amendment:read'])]
    private ?\DateTimeInterface $dateSignature = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['amendment:read'])]
    private ?\DateTimeInterface $sentAt = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['amendment:read'])]
    private int $sentCount = 0;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Groups(['amendment:read'])]
    private ?string $deliveryChannel = null; // 'email', 'pdp', 'both'

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['amendment:read', 'amendment:write'])]
    private ?string $signatureClient = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: AmendmentStatus::class)]
    #[Groups(['amendment:read', 'amendment:write'])]
    #[Assert\NotNull(message: "Le statut est obligatoire")]
    private ?AmendmentStatus $statut = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['amendment:read', 'amendment:write'])]
    private ?string $notes = null;

    #[ORM\Column(length: 36)]
    #[Assert\NotBlank(message: 'Le company_id est obligatoire')]
    #[Groups(['amendment:read', 'amendment:write'])]
    private ?string $companyId = null;

    /**
     * Nom du fichier PDF généré
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['amendment:read'])]
    private ?string $pdfFilename = null;

    /**
     * Hash SHA256 du PDF pour archivage légal (10 ans)
     */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    #[Groups(['amendment:read'])]
    private ?string $pdfHash = null;

    // ===== MONTANTS EN DECIMAL (EUROS) =====
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Groups(['amendment:read', 'amendment:write'])]
    #[Assert\NotBlank(message: 'Le montant HT est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le montant HT doit être un nombre')]
    private string $montantHT = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Groups(['amendment:read', 'amendment:write'])]
    #[Assert\NotBlank(message: 'Le montant TVA est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le montant TVA doit être un nombre')]
    private string $montantTVA = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => 0.00])]
    #[Groups(['amendment:read', 'amendment:write'])]
    #[Assert\NotBlank(message: 'Le montant TTC est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le montant TTC doit être un nombre')]
    private string $montantTTC = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => 0.00])]
    #[Groups(['amendment:read', 'amendment:write'])]
    private string $tauxTVA = '0.00';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['amendment:read'])]
    private ?\DateTimeInterface $dateModification = null;

    /**
     * @var Collection<int, AmendmentLine>
     */
    #[ORM\OneToMany(targetEntity: AmendmentLine::class, mappedBy: 'amendment', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['amendment:read', 'amendment:write'])]
    private Collection $lines;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->dateModification = new \DateTime();
        $this->statut = AmendmentStatus::DRAFT;
        $this->montantHT = '0.00';
        $this->montantTVA = '0.00';
        $this->montantTTC = '0.00';
        $this->tauxTVA = '0.00';
        $this->lines = new ArrayCollection();
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

    public function getDateSignature(): ?\DateTimeInterface
    {
        return $this->dateSignature;
    }

    public function setDateSignature(?\DateTimeInterface $dateSignature): static
    {
        $this->dateSignature = $dateSignature;
        return $this;
    }

    public function getSentAt(): ?\DateTimeInterface
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeInterface $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getSentCount(): int
    {
        return $this->sentCount;
    }

    public function setSentCount(int $sentCount): static
    {
        $this->sentCount = $sentCount;
        return $this;
    }

    public function incrementSentCount(): static
    {
        $this->sentCount++;
        return $this;
    }

    public function getDeliveryChannel(): ?string
    {
        return $this->deliveryChannel;
    }

    public function setDeliveryChannel(?string $deliveryChannel): static
    {
        $this->deliveryChannel = $deliveryChannel;
        return $this;
    }

    public function getSignatureClient(): ?string
    {
        return $this->signatureClient;
    }

    public function setSignatureClient(?string $signatureClient): static
    {
        $this->signatureClient = $signatureClient;
        return $this;
    }

    public function getStatut(): ?AmendmentStatus
    {
        return $this->statut;
    }

    public function setStatut(?AmendmentStatus $statut): static
    {
        // Si passage à SIGNED, valider que c'est possible
        if ($statut === AmendmentStatus::SIGNED && $this->statut !== AmendmentStatus::SIGNED) {
            $this->validateCanBeSigned();
        }

        $this->statut = $statut;
        return $this;
    }

    public function getStatutEnum(): ?AmendmentStatus
    {
        return $this->statut;
    }

    public function setStatutEnum(AmendmentStatus $statut): static
    {
        return $this->setStatut($statut);
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
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

    public function getTauxTVA(): string
    {
        return $this->tauxTVA;
    }

    public function setTauxTVA(string $tauxTVA): static
    {
        $this->tauxTVA = $tauxTVA;
        return $this;
    }

    // ===== RELATION AVEC LES LIGNES =====

    /**
     * @return Collection<int, AmendmentLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(AmendmentLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setAmendment($this);
        }
        return $this;
    }

    public function removeLine(AmendmentLine $line): static
    {
        if ($this->lines->removeElement($line)) {
            if ($line->getAmendment() === $this) {
                $line->setAmendment(null);
            }
        }
        return $this;
    }

    // ===== MÉTHODES DE CALCUL AUTOMATIQUE =====

    /**
     * Recalcule les montants HT/TTC depuis les lignes
     * Les montants sont stockés en DECIMAL (euros)
     */
    public function recalculateTotalsFromLines(): void
    {
        if ($this->lines->isEmpty()) {
            $this->montantHT = '0.00';
            $this->montantTVA = '0.00';
            $this->montantTTC = '0.00';
            return;
        }

        $totalHtEuros = 0.0;
        $totalTvaEuros = 0.0;

        // Récupérer le devis associé pour connaître le mode de TVA
        $quote = $this->quote;
        
        // Déterminer si on utilise la TVA par ligne :
        // - Si un devis est associé, utiliser usePerLineTva du devis
        // - Sinon, détecter automatiquement si au moins une ligne a un taux de TVA défini
        $usePerLineTva = false;
        if ($quote) {
            $usePerLineTva = $quote->isUsePerLineTva();
        } else {
            // Détecter automatiquement : si au moins une ligne a un taux de TVA, on utilise la TVA par ligne
            foreach ($this->lines as $line) {
                if ($line->getTvaRate() && (float) $line->getTvaRate() > 0) {
                    $usePerLineTva = true;
                    break;
                }
            }
        }

        foreach ($this->lines as $line) {
            $lineTotalHt = (float) ($line->getTotalHt() ?? 0);
            $totalHtEuros += $lineTotalHt;

            // Déterminer le taux de TVA à utiliser pour cette ligne
            $tvaRate = null;
            
            if ($usePerLineTva && $line->getSourceLine()) {
                // TVA par ligne : pour une modification, utiliser le taux de la ligne source
                $sourceTvaRate = $line->getSourceLine()->getTvaRate();
                if ($sourceTvaRate && (float) $sourceTvaRate > 0) {
                    $tvaRate = (float) $sourceTvaRate;
                }
            }
            
            // Si pas de taux depuis la source, utiliser celui de la ligne d'avenant
            if ($tvaRate === null && $line->getTvaRate() && (float) $line->getTvaRate() > 0) {
                $tvaRate = (float) $line->getTvaRate();
            }
            
            // Si toujours pas de taux, utiliser le taux global de l'avenant
            if ($tvaRate === null && $this->tauxTVA && (float) $this->tauxTVA > 0) {
                $tvaRate = (float) $this->tauxTVA;
            }
            
            // Si toujours pas de taux, utiliser le taux global du devis
            if ($tvaRate === null && $quote && $quote->getTauxTVA() && (float) $quote->getTauxTVA() > 0) {
                $tvaRate = (float) $quote->getTauxTVA();
            }

            // Calculer la TVA de cette ligne
            if ($tvaRate !== null && $tvaRate > 0) {
                $tvaAmount = $lineTotalHt * ($tvaRate / 100);
                $totalTvaEuros += $tvaAmount;
            }
        }

        $this->montantHT = number_format($totalHtEuros, 2, '.', '');
        $this->montantTVA = number_format($totalTvaEuros, 2, '.', '');
        $this->montantTTC = number_format($totalHtEuros + $totalTvaEuros, 2, '.', '');
    }

    /**
     * Valide que l'avenant peut être signé
     * 
     * @throws \RuntimeException si l'avenant ne peut pas être signé
     */
    public function validateCanBeSigned(): void
    {
        // Vérifier qu'au moins une ligne est présente
        if ($this->lines->isEmpty()) {
            throw new \RuntimeException('Un avenant ne peut pas être signé sans ligne.');
        }

        // Vérifier que le montant HT est positif
        if ((float) $this->montantHT <= 0) {
            throw new \RuntimeException('Un avenant ne peut pas être signé avec un montant HT négatif ou nul.');
        }

        // Vérifier que le montant TTC est positif
        if ((float) $this->montantTTC <= 0) {
            throw new \RuntimeException('Un avenant ne peut pas être signé avec un montant TTC négatif ou nul.');
        }

        // Vérifier que l'avenant n'est pas annulé
        if ($this->statut === AmendmentStatus::CANCELLED) {
            throw new \RuntimeException('Un avenant annulé ne peut pas être signé.');
        }

        // Vérifier que le devis associé est signé
        if (!$this->quote || $this->quote->getStatut() !== QuoteStatus::SIGNED) {
            throw new \RuntimeException('Un avenant ne peut être créé que pour un devis signé.');
        }
    }

    // ===== MÉTHODES DE STATUT =====

    public function canBeModified(): bool
    {
        return $this->statut && $this->statut->isModifiable();
    }

    public function canBeSigned(): bool
    {
        return $this->statut === AmendmentStatus::SENT;
    }

    public function canBeCancelled(): bool
    {
        return $this->statut === AmendmentStatus::DRAFT;
    }

    public function getStatutLabel(): string
    {
        return $this->statut?->getLabel() ?? 'Inconnu';
    }

    public function getStatutColor(): string
    {
        return $this->statut?->getColor() ?? 'secondary';
    }

    // ===== MÉTHODES D'AFFICHAGE =====

    public function getMontantHTFormate(): string
    {
        $montant = (float) $this->montantHT; // Déjà en euros (DECIMAL)
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    public function getMontantTVAFormate(): string
    {
        $montant = (float) $this->montantTVA; // Déjà en euros (DECIMAL)
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    public function getMontantTTCFormate(): string
    {
        $montant = (float) $this->montantTTC; // Déjà en euros (DECIMAL)
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    public function getDocumentInfo(): string
    {
        if (!$this->quote) {
            return 'Devis inconnu';
        }

        return sprintf(
            'Devis %s - %s (%s)',
            $this->quote->getNumero(),
            $this->quote->getClient()?->getNomComplet() ?? 'Client inconnu',
            $this->quote->getMontantTTCFormate()
        );
    }

    public function __toString(): string
    {
        return $this->numero ?? 'Avenant #' . $this->id;
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
