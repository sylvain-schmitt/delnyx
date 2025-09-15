<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Entité Avenant pour gérer les modifications des devis et factures émis
 */
#[ORM\Entity]
#[ORM\Table(name: 'avenants')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['avenant:read']],
    denormalizationContext: ['groups' => ['avenant:write']],
    paginationItemsPerPage: 20
)]
class Avenant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['avenant:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['avenant:read', 'avenant:write'])]
    #[Assert\NotBlank]
    private ?string $numero = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['avenant:read', 'avenant:write'])]
    #[Assert\NotBlank]
    private ?string $typeDocument = null; // 'devis' ou 'facture'

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['avenant:read', 'avenant:write'])]
    #[Assert\NotBlank]
    private ?int $documentId = null; // ID du devis ou de la facture

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['avenant:read', 'avenant:write'])]
    #[Assert\NotBlank]
    private ?string $documentNumero = null; // Numéro du devis ou de la facture

    // Relations optionnelles pour faciliter l'accès aux documents
    #[ORM\ManyToOne(targetEntity: Devis::class)]
    #[ORM\JoinColumn(name: 'devis_id', referencedColumnName: 'id', nullable: true)]
    private ?Devis $devis = null;

    #[ORM\ManyToOne(targetEntity: Facture::class)]
    #[ORM\JoinColumn(name: 'facture_id', referencedColumnName: 'id', nullable: true)]
    private ?Facture $facture = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['avenant:read', 'avenant:write'])]
    #[Assert\NotBlank]
    private ?string $motif = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['avenant:read', 'avenant:write'])]
    #[Assert\NotBlank]
    private ?string $modifications = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['avenant:read', 'avenant:write'])]
    private ?string $justification = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['avenant:read'])]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['avenant:read', 'avenant:write'])]
    private ?\DateTimeInterface $dateValidation = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['avenant:read', 'avenant:write'])]
    #[Assert\NotBlank]
    private ?string $statut = 'brouillon'; // brouillon, valide, rejete

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['avenant:read', 'avenant:write'])]
    private ?string $notes = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->statut = 'brouillon';
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

    public function getTypeDocument(): ?string
    {
        return $this->typeDocument;
    }

    public function setTypeDocument(string $typeDocument): static
    {
        $this->typeDocument = $typeDocument;
        return $this;
    }

    public function getDocumentId(): ?int
    {
        return $this->documentId;
    }

    public function setDocumentId(int $documentId): static
    {
        $this->documentId = $documentId;
        return $this;
    }

    public function getDocumentNumero(): ?string
    {
        return $this->documentNumero;
    }

    public function setDocumentNumero(string $documentNumero): static
    {
        $this->documentNumero = $documentNumero;
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function __toString(): string
    {
        return $this->numero ?? 'Avenant #' . $this->id;
    }

    public function getStatutLabel(): string
    {
        return match ($this->statut) {
            'brouillon' => 'Brouillon',
            'valide' => 'Validé',
            'rejete' => 'Rejeté',
            default => 'Inconnu'
        };
    }

    public function getStatutColor(): string
    {
        return match ($this->statut) {
            'brouillon' => 'warning',
            'valide' => 'success',
            'rejete' => 'danger',
            default => 'secondary'
        };
    }

    public function isValide(): bool
    {
        return $this->statut === 'valide';
    }

    public function isBrouillon(): bool
    {
        return $this->statut === 'brouillon';
    }

    public function isRejete(): bool
    {
        return $this->statut === 'rejete';
    }

    public function getDevis(): ?Devis
    {
        return $this->devis;
    }

    public function setDevis(?Devis $devis): static
    {
        $this->devis = $devis;
        if ($devis) {
            $this->documentId = $devis->getId();
            $this->documentNumero = $devis->getNumero();
            $this->typeDocument = 'devis';
        }
        return $this;
    }

    public function getFacture(): ?Facture
    {
        return $this->facture;
    }

    public function setFacture(?Facture $facture): static
    {
        $this->facture = $facture;
        if ($facture) {
            $this->documentId = $facture->getId();
            $this->documentNumero = $facture->getNumero();
            $this->typeDocument = 'facture';
        }
        return $this;
    }

    public function getDocument(): Devis|Facture|null
    {
        return $this->devis ?? $this->facture;
    }

    public function getDocumentInfo(): string
    {
        $document = $this->getDocument();
        if (!$document) {
            return $this->documentNumero ?? 'Document inconnu';
        }

        return sprintf(
            '%s - %s (%s)',
            $this->typeDocument === 'devis' ? 'Devis' : 'Facture',
            $document->getNumero(),
            $document->getClient()?->getNomComplet() ?? 'Client inconnu'
        );
    }
}
