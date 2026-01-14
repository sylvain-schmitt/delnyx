<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CompanySettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;

#[ORM\Entity(repositoryClass: CompanySettingsRepository::class)]
#[ORM\Table(name: 'company_settings')]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
        new Patch()
    ],
    normalizationContext: ['groups' => ['company_settings:read']],
    denormalizationContext: ['groups' => ['company_settings:write']]
)]
class CompanySettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['company_settings:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 36, unique: true)]
    #[Assert\NotBlank(message: 'Le company_id est obligatoire')]
    #[Assert\Length(exactly: 36, exactMessage: 'Le company_id doit faire exactement 36 caractères (UUID)')]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private ?string $companyId = null;

    // ===== CONFIGURATION TVA =====

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private bool $tvaEnabled = false;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => '0.00'])]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le taux de TVA ne peut pas être négatif')]
    #[Assert\LessThanOrEqual(value: 100, message: 'Le taux de TVA ne peut pas dépasser 100%')]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private ?string $tauxTVADefaut = '0.00';

    // ===== CONFIGURATION PDP (Plateforme de Dématérialisation Partenaire) =====

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'none'])]
    #[Assert\NotBlank]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private ?string $pdpMode = PDPMode::NONE->value;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private ?string $pdpProvider = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['company_settings:write'])] // Pas en read pour sécurité
    private ?string $pdpApiKey = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['company_settings:read'])]
    private ?string $pdpStatus = null;

    // ===== INFORMATIONS ENTREPRISE =====

    #[ORM\Column(type: Types::STRING, length: 9, nullable: true)]
    #[Assert\Length(exactly: 9, exactMessage: 'Le SIREN doit faire exactement 9 caractères')]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private ?string $siren = null;

    #[ORM\Column(type: Types::STRING, length: 14, nullable: true)]
    #[Assert\Length(exactly: 14, exactMessage: 'Le SIRET doit faire exactement 14 caractères')]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private ?string $siret = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'La raison sociale est obligatoire')]
    #[Assert\Length(max: 255, maxMessage: 'La raison sociale ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private ?string $raisonSociale = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'L\'adresse est obligatoire')]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private ?string $adresse = null;

    #[ORM\Column(type: Types::STRING, length: 10)]
    #[Assert\NotBlank(message: 'Le code postal est obligatoire')]
    #[Assert\Length(max: 10, maxMessage: 'Le code postal ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private ?string $codePostal = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'La ville est obligatoire')]
    #[Assert\Length(max: 100, maxMessage: 'La ville ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private ?string $ville = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Email(message: 'L\'email n\'est pas valide')]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private ?string $email = null; // Optionnel : si vide, utiliser l'email du User

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private ?string $telephone = null;

    // ===== CONFIGURATION SIGNATURE ÉLECTRONIQUE =====

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private ?string $signatureProvider = null; // 'custom', 'yousign', 'docusign'

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['company_settings:write'])] // Pas en read pour sécurité
    private ?string $signatureApiKey = null;

    // ===== LOGO ENTREPRISE =====

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private ?string $logoPath = null; // Chemin vers le fichier logo

    // ===== MENTIONS LÉGALES OBLIGATOIRES =====

    #[ORM\Column(type: Types::STRING, length: 100, options: ['default' => 'Auto-entrepreneur'])]
    #[Assert\NotBlank(message: 'La forme juridique est obligatoire')]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private string $formeJuridique = 'Auto-entrepreneur';

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    #[Assert\Length(max: 10, maxMessage: 'Le code APE ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private ?string $codeAPE = null; // Code NAF/APE de l'activité

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private ?string $assuranceRCPro = null; // Nom et coordonnées de l'assurance RC Pro

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => '40.00'])]
    #[Assert\PositiveOrZero(message: 'L\'indemnité forfaitaire ne peut pas être négative')]
    #[Groups(['company_settings:read', 'company_settings:write'])]
    private string $indemniteForfaitaireRecouvrement = '40.00'; // Montant légal minimum : 40€

    public function getId(): ?int
    {
        return $this->id;
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

    public function isTvaEnabled(): bool
    {
        return $this->tvaEnabled;
    }

    public function setTvaEnabled(bool $tvaEnabled): static
    {
        $this->tvaEnabled = $tvaEnabled;

        return $this;
    }

    public function getTauxTVADefaut(): ?string
    {
        return $this->tauxTVADefaut;
    }

    public function setTauxTVADefaut(string $tauxTVADefaut): static
    {
        $this->tauxTVADefaut = $tauxTVADefaut;

        return $this;
    }

    public function getPdpMode(): ?string
    {
        return $this->pdpMode;
    }

    public function setPdpMode(string $pdpMode): static
    {
        $this->pdpMode = $pdpMode;

        return $this;
    }

    public function getPdpModeEnum(): ?PDPMode
    {
        return $this->pdpMode ? PDPMode::from($this->pdpMode) : null;
    }

    public function setPdpModeEnum(PDPMode $pdpMode): static
    {
        $this->pdpMode = $pdpMode->value;

        return $this;
    }

    public function getPdpProvider(): ?string
    {
        return $this->pdpProvider;
    }

    public function setPdpProvider(?string $pdpProvider): static
    {
        $this->pdpProvider = $pdpProvider;

        return $this;
    }

    public function getPdpApiKey(): ?string
    {
        return $this->pdpApiKey;
    }

    public function setPdpApiKey(?string $pdpApiKey): static
    {
        $this->pdpApiKey = $pdpApiKey;

        return $this;
    }

    public function getPdpStatus(): ?string
    {
        return $this->pdpStatus;
    }

    public function setPdpStatus(?string $pdpStatus): static
    {
        $this->pdpStatus = $pdpStatus;

        return $this;
    }

    public function getSiren(): ?string
    {
        return $this->siren;
    }

    public function setSiren(?string $siren): static
    {
        $this->siren = $siren;

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

    public function getRaisonSociale(): ?string
    {
        return $this->raisonSociale;
    }

    public function setRaisonSociale(string $raisonSociale): static
    {
        $this->raisonSociale = $raisonSociale;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(string $codePostal): static
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;

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

    public function getSignatureProvider(): ?string
    {
        return $this->signatureProvider;
    }

    public function setSignatureProvider(?string $signatureProvider): static
    {
        $this->signatureProvider = $signatureProvider;

        return $this;
    }

    public function getSignatureApiKey(): ?string
    {
        return $this->signatureApiKey;
    }

    public function setSignatureApiKey(?string $signatureApiKey): static
    {
        $this->signatureApiKey = $signatureApiKey;

        return $this;
    }

    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function setLogoPath(?string $logoPath): static
    {
        $this->logoPath = $logoPath;

        return $this;
    }

    /**
     * Vérifie si la facturation électronique est activée
     */
    public function isPdpEnabled(): bool
    {
        $mode = $this->getPdpModeEnum();
        return $mode && $mode->isEnabled();
    }

    /**
     * Retourne l'adresse complète formatée
     */
    public function getAdresseComplete(): string
    {
        return sprintf(
            '%s, %s %s',
            $this->adresse ?? '',
            $this->codePostal ?? '',
            $this->ville ?? ''
        );
    }

    // ===== GETTERS/SETTERS MENTIONS LÉGALES =====

    public function getFormeJuridique(): string
    {
        return $this->formeJuridique;
    }

    public function setFormeJuridique(string $formeJuridique): self
    {
        $this->formeJuridique = $formeJuridique;

        return $this;
    }

    public function getCodeAPE(): ?string
    {
        return $this->codeAPE;
    }

    public function setCodeAPE(?string $codeAPE): self
    {
        $this->codeAPE = $codeAPE;

        return $this;
    }

    public function getAssuranceRCPro(): ?string
    {
        return $this->assuranceRCPro;
    }

    public function setAssuranceRCPro(?string $assuranceRCPro): self
    {
        $this->assuranceRCPro = $assuranceRCPro;

        return $this;
    }

    public function getIndemniteForfaitaireRecouvrement(): string
    {
        return $this->indemniteForfaitaireRecouvrement;
    }

    public function setIndemniteForfaitaireRecouvrement(string $indemniteForfaitaireRecouvrement): self
    {
        $this->indemniteForfaitaireRecouvrement = $indemniteForfaitaireRecouvrement;

        return $this;
    }

    /**
     * Retourne l'indemnité forfaitaire formatée pour affichage
     */
    public function getIndemniteForfaitaireRecouvrementFormatee(): string
    {
        return number_format((float) $this->indemniteForfaitaireRecouvrement, 2, ',', ' ') . ' €';
    }
}
