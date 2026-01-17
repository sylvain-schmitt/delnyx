<?php

namespace App\Repository;

use App\Entity\ReminderRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReminderRule>
 */
class ReminderRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReminderRule::class);
    }

    /**
     * Trouve toutes les règles actives pour une entreprise, triées par ordre
     *
     * @return ReminderRule[]
     */
    public function findActiveRules(?string $companyId = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('r.daysAfterDue', 'ASC')
            ->addOrderBy('r.ordre', 'ASC');

        if ($companyId !== null) {
            $qb->andWhere('r.companyId = :companyId OR r.companyId IS NULL')
                ->setParameter('companyId', $companyId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve toutes les règles pour une entreprise
     *
     * @return ReminderRule[]
     */
    public function findByCompanyId(?string $companyId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.companyId = :companyId OR r.companyId IS NULL')
            ->setParameter('companyId', $companyId)
            ->orderBy('r.daysAfterDue', 'ASC')
            ->addOrderBy('r.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
