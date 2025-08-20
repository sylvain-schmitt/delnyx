<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use App\Repository\ProjectImageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjectImageRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['project_image:read']]
        ),
        new Get(
            normalizationContext: ['groups' => ['project_image:read']]
        ),
        new Post(
            normalizationContext: ['groups' => ['project_image:read']],
            denormalizationContext: ['groups' => ['project_image:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['project_image:read']],
            denormalizationContext: ['groups' => ['project_image:write']]
        ),
        new Delete()
    ]
)]
class ProjectImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['project_image:read', 'project:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du fichier est obligatoire')]
    #[Groups(['project_image:read', 'project_image:write', 'project:read'])]
    private ?string $fichier = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le texte alternatif ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['project_image:read', 'project_image:write', 'project:read'])]
    private ?string $altText = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'L\'ordre doit être un nombre positif ou zéro')]
    #[Groups(['project_image:read', 'project_image:write', 'project:read'])]
    private int $ordre = 0;

    #[ORM\ManyToOne(inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le projet est obligatoire')]
    #[Groups(['project_image:read', 'project_image:write'])]
    private ?Project $projet = null;

    #[ORM\Column]
    #[Groups(['project_image:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Groups(['project_image:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFichier(): ?string
    {
        return $this->fichier;
    }

    public function setFichier(string $fichier): static
    {
        $this->fichier = $fichier;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function setAltText(?string $altText): static
    {
        $this->altText = $altText;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getProjet(): ?Project
    {
        return $this->projet;
    }

    public function setProjet(?Project $projet): static
    {
        $this->projet = $projet;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Retourne l'URL complète de l'image
     */
    public function getImageUrl(): string
    {
        return '/uploads/projects/' . $this->fichier;
    }

    /**
     * Retourne l'URL de la miniature
     */
    public function getThumbnailUrl(): string
    {
        $pathInfo = pathinfo($this->fichier);
        return '/uploads/projects/thumbnails/' . $pathInfo['filename'] . '_thumb.' . $pathInfo['extension'];
    }

    public function __toString(): string
    {
        return $this->altText ?? $this->fichier ?? 'Image #' . $this->id;
    }
}
