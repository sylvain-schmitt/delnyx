<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PurchaseInvoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PurchaseInvoice>
 */
class PurchaseInvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PurchaseInvoice::class);
    }

    public function findByCompanyId(string $companyId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.companyId = :companyId')
            ->setParameter('companyId', $companyId)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByPdpInvoiceId(string $companyId, int $pdpInvoiceId): ?PurchaseInvoice
    {
        return $this->createQueryBuilder('p')
            ->where('p.companyId = :companyId')
            ->andWhere('p.pdpInvoiceId = :pdpInvoiceId')
            ->setParameter('companyId', $companyId)
            ->setParameter('pdpInvoiceId', $pdpInvoiceId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countForCompany(string $companyId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.companyId = :companyId')
            ->setParameter('companyId', $companyId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumTotalTTCForCompanyThisMonth(string $companyId): float
    {
        $start = new \DateTime('first day of this month 00:00:00');
        $end   = new \DateTime('last day of this month 23:59:59');

        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.totalTTC)')
            ->where('p.companyId = :companyId')
            ->andWhere('p.createdAt >= :start')
            ->andWhere('p.createdAt <= :end')
            ->setParameter('companyId', $companyId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0.0);
    }

    public function countPendingForCompany(string $companyId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.companyId = :companyId')
            ->andWhere('p.localStatus IS NULL')
            ->setParameter('companyId', $companyId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
