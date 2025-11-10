<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CreditNoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;

#[ORM\Entity(repositoryClass: CreditNoteRepository::class)]
#[ORM\Table(name: 'credit_notes')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
        new Delete()
    ],
    normalizationContext: ['groups' => ['credit_note:read']],
    denormalizationContext: ['groups' => ['credit_note:write']],
    paginationItemsPerPage: 20
)]
class CreditNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['credit_note:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    #[Assert\NotBlank(message: 'Le numéro d\'avoir est obligatoire')]
    #[Assert\Length(max: 30, maxMessage: 'Le numéro ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    private ?string $number = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire')]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    private ?string $status = 'draft';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    private ?string $reason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['credit_note:read'])]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    private ?\DateTimeImmutable $dateEmission = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Le montant HT est obligatoire')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le montant HT ne peut pas être négatif')]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    private ?int $montantHT = 0;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Le montant TVA est obligatoire')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le montant TVA ne peut pas être négatif')]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    private ?int $montantTVA = 0;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Le montant TTC est obligatoire')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le montant TTC ne peut pas être négatif')]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    private ?int $montantTTC = 0;

    #[ORM\Column(length: 36)]
    #[Assert\NotBlank(message: 'Le company_id est obligatoire')]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    private ?string $companyId = null;

    #[ORM\ManyToOne(inversedBy: 'creditNotes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'avoir doit être lié à une facture')]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    private ?Invoice $invoice = null;

    /**
     * @var Collection<int, CreditNoteLine>
     */
    #[ORM\OneToMany(targetEntity: CreditNoteLine::class, mappedBy: 'creditNote', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['credit_note:read', 'credit_note:write'])]
    private Collection $lines;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
        $this->lines = new ArrayCollection();
        $this->montantHT = 0;
        $this->montantTVA = 0;
        $this->montantTTC = 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getDateEmission(): ?\DateTimeImmutable
    {
        return $this->dateEmission;
    }

    public function setDateEmission(?\DateTimeImmutable $dateEmission): static
    {
        $this->dateEmission = $dateEmission;

        return $this;
    }

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

    public function getCompanyId(): ?string
    {
        return $this->companyId;
    }

    public function setCompanyId(string $companyId): static
    {
        $this->companyId = $companyId;

        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        $this->invoice = $invoice;

        return $this;
    }

    /**
     * @return Collection<int, CreditNoteLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(CreditNoteLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setCreditNote($this);
        }

        return $this;
    }

    public function removeLine(CreditNoteLine $line): static
    {
        if ($this->lines->removeElement($line)) {
            // set the owning side to null (unless already changed)
            if ($line->getCreditNote() === $this) {
                $line->setCreditNote(null);
            }
        }

        return $this;
    }

    /**
     * Recalcule les montants HT, TVA et TTC à partir des lignes
     */
    public function recalculateTotals(): void
    {
        $totalHT = 0;
        $totalTVA = 0;

        foreach ($this->lines as $line) {
            $totalHT += $line->getTotalHt() ?? 0;
            
            if ($line->getTvaRate() && (float) $line->getTvaRate() > 0) {
                $tvaAmount = (int) round($line->getTotalHt() * ((float) $line->getTvaRate() / 100));
                $totalTVA += $tvaAmount;
            }
        }

        $this->montantHT = $totalHT;
        $this->montantTVA = $totalTVA;
        $this->montantTTC = $totalHT + $totalTVA;
    }

    /**
     * Retourne le montant HT formaté pour l'affichage
     */
    public function getMontantHTFormatted(): string
    {
        $montant = ($this->montantHT ?? 0) / 100; // Conversion centimes -> euros
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Retourne le montant TTC formaté pour l'affichage
     */
    public function getMontantTTCFormatted(): string
    {
        $montant = ($this->montantTTC ?? 0) / 100; // Conversion centimes -> euros
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Représentation string de l'avoir
     */
    public function __toString(): string
    {
        return sprintf('%s - %s', $this->number ?? 'Credit Note #' . $this->id, $this->getMontantTTCFormatted());
    }

    #[ORM\PrePersist]
    public function setDateCreationValue(): void
    {
        if (!$this->dateCreation) {
            $this->dateCreation = new \DateTimeImmutable();
        }
    }
}

