<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;

#[ORM\Entity]
#[ORM\Table(name: 'invoices')]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(
            security: "is_granted('ROLE_USER') && (object.getQuote() === null || (object.getQuote() !== null && object.getQuote().getStatut() !== null && object.getQuote().getStatut().value === 'signed'))",
            securityMessage: "Seuls les utilisateurs authentifiés peuvent créer des factures via l'API. Si un devis est associé, il doit être signé."
        ),
        new Put(
            security: "is_granted('INVOICE_EDIT', object)",
            securityMessage: "Seules les factures en brouillon peuvent être modifiées via l'API."
        ),
        new Patch(
            security: "is_granted('INVOICE_EDIT', object)",
            securityMessage: "Seules les factures en brouillon peuvent être modifiées via l'API."
        ),
        new Delete(
            security: "false",
            securityMessage: "Les factures ne peuvent pas être supprimées. Utilisez l'annulation."
        ),
        new Post(
            uriTemplate: '/invoices/{id}/issue',
            controller: \App\Controller\Api\InvoiceIssueController::class,
            security: "is_granted('INVOICE_ISSUE', object)",
            securityMessage: "Vous n'avez pas la permission d'émettre cette facture.",
            read: false,
            name: 'invoice_issue'
        ),
        new Post(
            uriTemplate: '/invoices/{id}/send',
            controller: \App\Controller\Api\InvoiceSendController::class,
            security: "is_granted('INVOICE_SEND', object)",
            securityMessage: "Vous n'avez pas la permission d'envoyer cette facture.",
            read: false,
            name: 'invoice_send'
        ),
        new Post(
            uriTemplate: '/invoices/{id}/mark-paid',
            controller: \App\Controller\Api\InvoiceMarkPaidController::class,
            security: "is_granted('INVOICE_MARK_PAID', object)",
            securityMessage: "Vous n'avez pas la permission de marquer cette facture comme payée.",
            read: false,
            name: 'invoice_mark_paid'
        )
    ],
    normalizationContext: ['groups' => ['invoice:read']],
    denormalizationContext: ['groups' => ['invoice:write']],
    paginationItemsPerPage: 20
)]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['invoice:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\Length(max: 50)]
    private ?string $numero = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['invoice:read'])]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $dateEcheance = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\NotBlank]
    private ?string $statut = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\PositiveOrZero]
    private ?string $montantHT = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\PositiveOrZero]
    private ?string $montantTVA = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\PositiveOrZero]
    private ?string $montantTTC = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\PositiveOrZero]
    private ?string $montantAcompte = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $conditionsPaiement = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\PositiveOrZero]
    private ?int $delaiPaiement = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\PositiveOrZero]
    private ?string $penalitesRetard = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?\DateTimeInterface $datePaiement = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?\DateTimeInterface $dateEnvoi = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Groups(['invoice:read'])]
    private int $sentCount = 0;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $deliveryChannel = null; // 'email', 'pdp', 'both'

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?\DateTimeInterface $dateModification = null;

    #[ORM\Column(length: 36)]
    #[Assert\NotBlank(message: 'Le company_id est obligatoire')]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $companyId = null;

    /**
     * Nom du fichier PDF généré
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $pdfFilename = null;

    /**
     * Hash SHA256 du PDF pour archivage légal (10 ans)
     */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $pdfHash = null;

    // ===== CHAMPS PDP (Plateforme de Dématérialisation Partenaire) =====

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $pdpStatus = null; // ACCEPTED, REJECTED, PENDING

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $pdpProvider = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?\DateTimeInterface $pdpTransmissionDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $pdpResponse = null; // Réponse de la PDP (JSON ou texte)

    // Relations
    #[ORM\OneToOne(targetEntity: Quote::class, inversedBy: 'invoice')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?Quote $quote = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'invoices')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[Assert\NotBlank]
    private ?Client $client = null;

    /**
     * @var Collection<int, InvoiceLine>
     */
    #[ORM\OneToMany(targetEntity: InvoiceLine::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private Collection $lines;

    /**
     * @var Collection<int, CreditNote>
     */
    #[ORM\OneToMany(targetEntity: CreditNote::class, mappedBy: 'invoice')]
    #[Groups(['invoice:read'])]
    private Collection $creditNotes;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->dateModification = new \DateTime();
        $this->statut = InvoiceStatus::DRAFT->value;
        $this->lines = new ArrayCollection();
        $this->creditNotes = new ArrayCollection();
    }
    #[ORM\PreUpdate]
    public function checkImmutability(PreUpdateEventArgs $args): void
    {
        // Une fois émise, une facture ne peut plus être modifiée (sauf certains champs techniques)
        if (!$this->canBeModified()) {
            $changedFields = array_keys($args->getEntityChangeSet());
            
            // Liste des champs autorisés même sur facture émise (champs techniques/métadonnées)
            $allowedFields = [
                'datePaiement', 
                'sentAt', 
                'sentCount', 
                'dateModification',
                'pdfFilename',  // Métadonnée PDF
                'pdfHash'       // Métadonnée PDF
            ];
            
            $unauthorizedChanges = array_diff($changedFields, $allowedFields);
            
            if (!empty($unauthorizedChanges)) {
                throw new \RuntimeException(
                    sprintf(
                        'La facture #%s est émise et ne peut plus être modifiée. Le champ "%s" ne peut pas être modifié.',
                        $this->numero,
                        implode('", "', $unauthorizedChanges)
                    )
                );
            }
        }
    }

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
        // Empêcher la modification du numéro si la facture est émise
        if ($this->id !== null && $this->numero !== null && $this->numero !== $numero) {
            $statutEnum = $this->getStatutEnum();
            if ($statutEnum && $statutEnum->isEmitted()) {
                throw new \RuntimeException(
                    sprintf(
                        'Le numéro de la facture #%s ne peut pas être modifié car elle est déjà émise.',
                        $this->numero
                    )
                );
            }
        }
        
        $this->numero = $numero;
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

    public function getDateEcheance(): ?\DateTimeInterface
    {
        return $this->dateEcheance;
    }

    public function setDateEcheance(\DateTimeInterface $dateEcheance): self
    {
        $this->dateEcheance = $dateEcheance;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        $this->dateModification = new \DateTime();
        return $this;
    }

    public function getStatutEnum(): ?InvoiceStatus
    {
        return $this->statut ? InvoiceStatus::from($this->statut) : null;
    }

    public function setStatutEnum(InvoiceStatus $statut): self
    {
        $this->statut = $statut->value;
        $this->dateModification = new \DateTime();
        return $this;
    }

    public function getMontantHT(): ?string
    {
        return $this->montantHT;
    }

    public function setMontantHT(string $montantHT): self
    {
        $this->montantHT = $montantHT;
        return $this;
    }

    public function getMontantTVA(): ?string
    {
        return $this->montantTVA;
    }

    public function setMontantTVA(string $montantTVA): self
    {
        $this->montantTVA = $montantTVA;
        return $this;
    }

    public function getMontantTTC(): ?string
    {
        return $this->montantTTC;
    }

    public function setMontantTTC(string $montantTTC): self
    {
        $this->montantTTC = $montantTTC;
        return $this;
    }

    public function getMontantAcompte(): ?string
    {
        return $this->montantAcompte;
    }

    public function setMontantAcompte(?string $montantAcompte): self
    {
        $this->montantAcompte = $montantAcompte;
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

    public function getDelaiPaiement(): ?int
    {
        return $this->delaiPaiement;
    }

    public function setDelaiPaiement(?int $delaiPaiement): self
    {
        $this->delaiPaiement = $delaiPaiement;
        return $this;
    }

    public function getPenalitesRetard(): ?string
    {
        return $this->penalitesRetard;
    }

    public function setPenalitesRetard(?string $penalitesRetard): self
    {
        $this->penalitesRetard = $penalitesRetard;
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

    public function getDatePaiement(): ?\DateTimeInterface
    {
        return $this->datePaiement;
    }

    public function setDatePaiement(?\DateTimeInterface $datePaiement): self
    {
        $this->datePaiement = $datePaiement;
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

    public function getDateModification(): ?\DateTimeInterface
    {
        return $this->dateModification;
    }

    public function setDateModification(?\DateTimeInterface $dateModification): self
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

    public function getPdpStatus(): ?string
    {
        return $this->pdpStatus;
    }

    public function setPdpStatus(?string $pdpStatus): self
    {
        $this->pdpStatus = $pdpStatus;
        return $this;
    }

    public function getPdpProvider(): ?string
    {
        return $this->pdpProvider;
    }

    public function setPdpProvider(?string $pdpProvider): self
    {
        $this->pdpProvider = $pdpProvider;
        return $this;
    }

    public function getPdpTransmissionDate(): ?\DateTimeInterface
    {
        return $this->pdpTransmissionDate;
    }

    public function setPdpTransmissionDate(?\DateTimeInterface $pdpTransmissionDate): self
    {
        $this->pdpTransmissionDate = $pdpTransmissionDate;
        return $this;
    }

    public function getPdpResponse(): ?string
    {
        return $this->pdpResponse;
    }

    public function setPdpResponse(?string $pdpResponse): self
    {
        $this->pdpResponse = $pdpResponse;
        return $this;
    }

    public function getQuote(): ?Quote
    {
        return $this->quote;
    }

    public function setQuote(?Quote $quote): self
    {
        // Empêcher le changement de devis si la facture est émise
        if ($this->id !== null && $this->quote !== null && $this->quote !== $quote) {
            $statutEnum = $this->getStatutEnum();
            if ($statutEnum && $statutEnum->isEmitted()) {
                throw new \RuntimeException(
                    sprintf(
                        'Le devis associé à la facture #%s ne peut pas être modifié car elle est déjà émise.',
                        $this->numero ?? $this->id
                    )
                );
            }
        }
        
        $this->quote = $quote;
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

    // ===== MÉTHODES MÉTIER =====

    /**
     * Retourne le montant restant à payer
     * Les montants sont stockés en DECIMAL (euros), pas besoin de diviser par 100
     */
    public function getMontantRestant(): string
    {
        $montantTTC = (float) $this->montantTTC; // Déjà en euros (DECIMAL)
        $montantAcompte = (float) ($this->montantAcompte ?? 0); // Déjà en euros (DECIMAL)

        return number_format($montantTTC - $montantAcompte, 2, '.', '');
    }

    /**
     * Retourne le solde final de la facture après déduction des avoirs émis
     * Solde = Montant facture TTC + Total avoirs émis (car les avoirs sont stockés en négatif)
     * Les montants sont stockés en DECIMAL (euros)
     */
    public function getSoldeFinal(): string
    {
        $montantTTC = (float) $this->montantTTC;
        $totalAvoirsEmis = 0.0;

        // Calculer le total des avoirs émis uniquement
        // Les avoirs sont stockés en montants négatifs, donc on les additionne directement
        foreach ($this->creditNotes as $creditNote) {
            $statutEnum = $creditNote->getStatutEnum();
            if ($statutEnum && $statutEnum === \App\Entity\CreditNoteStatus::ISSUED) {
                // Les avoirs sont stockés en négatif, donc on additionne directement
                $totalAvoirsEmis += (float) $creditNote->getMontantTTC();
            }
        }

        // Si les avoirs sont négatifs, additionner revient à soustraire
        // Exemple : 200 + (-200) = 0
        return number_format($montantTTC + $totalAvoirsEmis, 2, '.', '');
    }

    /**
     * Retourne le solde final formaté pour l'affichage
     */
    public function getSoldeFinalFormate(): string
    {
        return number_format((float) $this->getSoldeFinal(), 2, ',', ' ') . ' €';
    }

    /**
     * Vérifie si la facture est en retard
     */
    public function isEnRetard(): bool
    {
        if (!$this->dateEcheance || $this->statut === InvoiceStatus::PAID->value) {
            return false;
        }

        return new \DateTime() > $this->dateEcheance;
    }

    /**
     * Retourne le nombre de jours de retard
     */
    public function getJoursRetard(): int
    {
        if (!$this->isEnRetard()) {
            return 0;
        }

        $now = new \DateTime();
        $diff = $now->diff($this->dateEcheance);

        return $diff->days;
    }

    /**
     * Calcule les pénalités de retard
     */
    public function getMontantPenalites(): string
    {
        if (!$this->isEnRetard() || !$this->penalitesRetard) {
            return '0.00';
        }

        $montantRestant = (float) $this->getMontantRestant();
        $tauxPenalites = (float) $this->penalitesRetard;
        $joursRetard = $this->getJoursRetard();

        $penalites = ($montantRestant * $tauxPenalites / 100) * $joursRetard;

        return number_format($penalites, 2, '.', '');
    }

    /**
     * Vérifie si la facture est payée intégralement
     */
    public function isPayee(): bool
    {
        return $this->statut === InvoiceStatus::PAID->value;
    }

    /**
     * Vérifie si la facture peut être modifiée
     * Conformité légale : seules les factures en brouillon peuvent être modifiées
     */
    public function canBeModified(): bool
    {
        return $this->statut === InvoiceStatus::DRAFT->value;
    }

    /**
     * Vérifie si la facture peut être annulée
     */
    public function canBeCancelled(): bool
    {
        return !in_array($this->statut, [
            InvoiceStatus::PAID->value,
            InvoiceStatus::CANCELLED->value
        ]);
    }

    /**
     * Valide que la facture peut être émise
     * 
     * @throws \RuntimeException si la facture ne peut pas être émise
     */
    public function validateCanBeIssued(): void
    {
        // Vérifier le statut
        $statutEnum = $this->getStatutEnum();
        if (!$statutEnum || !$statutEnum->canBeIssued()) {
            throw new \RuntimeException(
                sprintf(
                    'La facture ne peut pas être émise depuis l\'état "%s".',
                    $statutEnum?->getLabel() ?? 'inconnu'
                )
            );
        }

        // Vérifier qu'il y a au moins une ligne
        if ($this->lines->isEmpty()) {
            throw new \RuntimeException('Une facture ne peut pas être émise sans ligne.');
        }

        // Vérifier que les montants sont cohérents
        if (empty($this->montantHT) || (float) $this->montantHT < 0) {
            throw new \RuntimeException('Le montant HT doit être positif.');
        }

        if (empty($this->montantTTC) || (float) $this->montantTTC < 0) {
            throw new \RuntimeException('Le montant TTC doit être positif.');
        }

        // Vérifier que la date d'échéance est définie
        if (!$this->dateEcheance) {
            throw new \RuntimeException('La date d\'échéance est obligatoire pour émettre une facture.');
        }

        // Vérifier que le client est défini
        if (!$this->client) {
            throw new \RuntimeException('Le client est obligatoire pour émettre une facture.');
        }
    }

    /**
     * Retourne le statut formaté pour l'affichage
     */
    public function getStatutLabel(): string
    {
        $statutEnum = $this->getStatutEnum();
        return $statutEnum ? $statutEnum->getLabel() : 'Non défini';
    }

    /**
     * Retourne la couleur du statut pour l'affichage
     */
    public function getStatutColor(): string
    {
        $statutEnum = $this->getStatutEnum();
        return $statutEnum ? $statutEnum->getColor() : 'secondary';
    }

    /**
     * Retourne le montant TTC formaté
     * Les montants sont stockés en DECIMAL (euros), pas besoin de diviser par 100
     */
    public function getMontantTTCFormate(): string
    {
        $montant = (float) $this->montantTTC; // Déjà en euros (DECIMAL)
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Retourne le montant HT formaté
     * Les montants sont stockés en DECIMAL (euros), pas besoin de diviser par 100
     */
    public function getMontantHTFormate(): string
    {
        $montant = (float) $this->montantHT; // Déjà en euros (DECIMAL)
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Retourne le montant TVA formaté
     * Les montants sont stockés en DECIMAL (euros), pas besoin de diviser par 100
     */
    public function getMontantTVAFormate(): string
    {
        $montant = (float) $this->montantTVA; // Déjà en euros (DECIMAL)
        return number_format($montant, 2, ',', ' ') . ' €';
    }

    /**
     * Retourne le montant restant formaté
     */
    public function getMontantRestantFormate(): string
    {
        return number_format((float) $this->getMontantRestant(), 2, ',', ' ') . ' €';
    }

    /**
     * Représentation string de la facture pour les listes déroulantes
     */
    public function __toString(): string
    {
        $client = $this->getClient() ? $this->getClient()->getNomComplet() : 'Client inconnu';
        return sprintf('%s - %s (%s)', $this->numero ?? 'Invoice #' . $this->id, $client, $this->getMontantTTCFormate());
    }

    /**
     * @return Collection<int, InvoiceLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(InvoiceLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setInvoice($this);
        }

        return $this;
    }

    public function removeLine(InvoiceLine $line): static
    {
        if ($this->lines->removeElement($line)) {
            // set the owning side to null (unless already changed)
            if ($line->getInvoice() === $this) {
                $line->setInvoice(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CreditNote>
     */
    public function getCreditNotes(): Collection
    {
        return $this->creditNotes;
    }

    public function addCreditNote(CreditNote $creditNote): static
    {
        if (!$this->creditNotes->contains($creditNote)) {
            $this->creditNotes->add($creditNote);
            $creditNote->setInvoice($this);
        }

        return $this;
    }

    public function removeCreditNote(CreditNote $creditNote): static
    {
        if ($this->creditNotes->removeElement($creditNote)) {
            // set the owning side to null (unless already changed)
            if ($creditNote->getInvoice() === $this) {
                $creditNote->setInvoice(null);
            }
        }

        return $this;
    }

    /**
     * Recalcule les montants HT/TTC depuis les lignes
     * - Si usePerLineTva = true (du quote): somme TTC par ligne
     * - Sinon: applique le taux global du devis sur le total HT (ou 0% si pas de devis)
     * Les montants sont stockés en DECIMAL (euros, string avec 2 décimales)
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

        $quote = $this->getQuote();
        
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
        
        $tauxTVA = $quote ? (float) $quote->getTauxTVA() : 0.0;

        foreach ($this->lines as $line) {
            $lineTotalHt = (float) ($line->getTotalHt() ?? 0);
            $totalHtEuros += $lineTotalHt;

            if ($usePerLineTva) {
                // TVA par ligne : calculer la TVA de chaque ligne
                if ($line->getTvaRate() && (float) $line->getTvaRate() > 0) {
                    $tvaAmount = $lineTotalHt * ((float) $line->getTvaRate() / 100);
                    $totalTvaEuros += $tvaAmount;
                }
            }
        }

        // Les montants sont déjà en euros (DECIMAL)
        $this->montantHT = number_format($totalHtEuros, 2, '.', '');

        if ($usePerLineTva) {
            // TVA par ligne : utiliser le total TVA calculé
            $this->montantTVA = number_format($totalTvaEuros, 2, '.', '');
            $this->montantTTC = number_format($totalHtEuros + $totalTvaEuros, 2, '.', '');
        } else {
            // TVA globale : appliquer le taux du devis sur le total HT (ou 0% si pas de devis)
            if ($tauxTVA > 0) {
                $tvaAmountEuros = $totalHtEuros * ($tauxTVA / 100);
                $this->montantTVA = number_format($tvaAmountEuros, 2, '.', '');
                $this->montantTTC = number_format($totalHtEuros + $tvaAmountEuros, 2, '.', '');
            } else {
                $this->montantTVA = '0.00';
                $this->montantTTC = number_format($totalHtEuros, 2, '.', '');
            }
        }
    }

    /**
     * Calcule le total corrigé de la facture en tenant compte des avoirs modifiables
     * totalCorrected = totalOriginal + sum(all deltas from modifiable credit notes)
     * 
     * CONFORMITÉ LÉGALE : 
     * - Les avoirs en brouillon (DRAFT) peuvent encore être modifiés, donc on les inclut dans le calcul
     * - Les avoirs émis (ISSUED) ou envoyés (SENT) sont définitifs et doivent être inclus
     * - Les avoirs annulés (CANCELLED) ne doivent pas être inclus
     */
    public function getTotalCorrected(): string
    {
        $totalOriginal = (float) $this->montantTTC;
        $totalDeltas = 0.0;

        // Somme des deltas de tous les avoirs modifiables (DRAFT) ou émis/envoyés (ISSUED, SENT)
        // Les avoirs annulés (CANCELLED) ne sont pas pris en compte
        foreach ($this->creditNotes as $creditNote) {
            $status = $creditNote->getStatutEnum();
            // Inclure tous les avoirs sauf ceux annulés (CANCELLED)
            // Cela inclut DRAFT, ISSUED, et SENT
            if ($status && $status !== \App\Entity\CreditNoteStatus::CANCELLED) {
                foreach ($creditNote->getLines() as $line) {
                    $totalDeltas += (float) $line->getDelta();
                }
            }
        }

        $totalCorrected = $totalOriginal + $totalDeltas;
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
}
