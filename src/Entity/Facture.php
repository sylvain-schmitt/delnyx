<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'factures')]
class Facture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['devis:read'])]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Devis::class, inversedBy: 'facture')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['devis:read'])]
    private ?Devis $devis = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'factures')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['devis:read'])]
    private ?Client $client = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDevis(): ?Devis
    {
        return $this->devis;
    }

    public function setDevis(?Devis $devis): self
    {
        $this->devis = $devis;
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

    /**
     * Retourne le montant TTC de la facture (depuis le devis associé)
     */
    public function getMontantTTC(): string
    {
        return $this->devis ? $this->devis->getMontantTTC() : '0.00';
    }

    /**
     * Retourne le statut de la facture (pour l'instant toujours "payée")
     * Plus tard, on ajoutera un vrai système de statuts
     */
    public function getStatut(): string
    {
        return 'payee'; // Pour l'instant, toutes les factures sont considérées comme payées
    }
}
