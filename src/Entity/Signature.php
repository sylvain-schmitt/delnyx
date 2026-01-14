<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SignatureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité Signature - Stockage des signatures électroniques
 * 
 * Stocke toutes les informations relatives à une signature client :
 * - Données de signature (canvas, image, texte)
 * - Informations du signataire
 * - Métadonnées de sécurité (IP, User-Agent, timestamp)
 */
#[ORM\Entity(repositoryClass: SignatureRepository::class)]
#[ORM\Table(name: 'signature')]
#[ORM\Index(columns: ['document_type', 'document_id'], name: 'idx_signature_document')]
#[ORM\Index(columns: ['signed_at'], name: 'idx_signature_date')]
class Signature
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Type de document signé (quote, amendment)
     */
    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\NotBlank(message: 'Le type de document est obligatoire')]
    #[Assert\Choice(choices: ['quote', 'amendment'], message: 'Type de document invalide')]
    private ?string $documentType = null;

    /**
     * ID du document signé
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank(message: 'L\'ID du document est obligatoire')]
    #[Assert\Positive(message: 'L\'ID doit être positif')]
    private ?int $documentId = null;

    /**
     * Nom du signataire
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Le nom du signataire est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $signerName = null;

    /**
     * Email du signataire
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'L\'email du signataire est obligatoire')]
    #[Assert\Email(message: 'L\'email n\'est pas valide')]
    private ?string $signerEmail = null;

    /**
     * Méthode de signature : text, draw, upload, yousign, docusign
     */
    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\NotBlank(message: 'La méthode de signature est obligatoire')]
    #[Assert\Choice(
        choices: ['text', 'draw', 'upload', 'yousign', 'docusign'],
        message: 'Méthode de signature invalide'
    )]
    private ?string $signatureMethod = null;

    /**
     * Données de la signature (JSON)
     * Pour text: {name: "..."}
     * Pour draw: {data: "data:image/png;base64,..."}
     * Pour upload: {filename: "...", data: "..."}
     * Pour yousign/docusign: {procedureId: "...", status: "..."}
     */
    #[ORM\Column(type: Types::JSON)]
    private array $signatureData = [];

    /**
     * Adresse IP du signataire
     */
    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $ipAddress = null;

    /**
     * User-Agent du navigateur
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    /**
     * Date et heure de la signature
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $signedAt = null;

    /**
     * Hash SHA-256 du document au moment de la signature
     * Pour preuve d'intégrité
     */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $documentHash = null;

    /**
     * Métadonnées supplémentaires (JSON)
     * Pour stocker des infos complémentaires selon le provider
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    public function __construct()
    {
        $this->signedAt = new \DateTimeImmutable();
    }

    // Getters & Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocumentType(): ?string
    {
        return $this->documentType;
    }

    public function setDocumentType(string $documentType): static
    {
        $this->documentType = $documentType;
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

    public function getSignerName(): ?string
    {
        return $this->signerName;
    }

    public function setSignerName(string $signerName): static
    {
        $this->signerName = $signerName;
        return $this;
    }

    public function getSignerEmail(): ?string
    {
        return $this->signerEmail;
    }

    public function setSignerEmail(string $signerEmail): static
    {
        $this->signerEmail = $signerEmail;
        return $this;
    }

    public function getSignatureMethod(): ?string
    {
        return $this->signatureMethod;
    }

    public function setSignatureMethod(string $signatureMethod): static
    {
        $this->signatureMethod = $signatureMethod;
        return $this;
    }

    public function getSignatureData(): array
    {
        return $this->signatureData;
    }

    public function setSignatureData(array $signatureData): static
    {
        $this->signatureData = $signatureData;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getSignedAt(): ?\DateTimeImmutable
    {
        return $this->signedAt;
    }

    public function setSignedAt(\DateTimeImmutable $signedAt): static
    {
        $this->signedAt = $signedAt;
        return $this;
    }

    public function getDocumentHash(): ?string
    {
        return $this->documentHash;
    }

    public function setDocumentHash(?string $documentHash): static
    {
        $this->documentHash = $documentHash;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }
}
