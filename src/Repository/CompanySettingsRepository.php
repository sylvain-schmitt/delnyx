<?php

namespace App\Repository;

use App\Entity\CompanySettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompanySettings>
 */
class CompanySettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanySettings::class);
    }

    /**
     * Trouve les paramètres d'une entreprise par son company_id
     */
    public function findByCompanyId(string $companyId): ?CompanySettings
    {
        return $this->findOneBy(['companyId' => $companyId]);
    }

    /**
     * Récupère le premier enregistrement CompanySettings (pour mode mono-tenant)
     */
    public function findFirst(): ?CompanySettings
    {
        return $this->findOneBy([]);
    }
}
