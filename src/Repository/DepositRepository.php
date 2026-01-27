<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Deposit;
use App\Entity\DepositStatus;
use App\Entity\Invoice;
use App\Entity\Quote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Deposit>
 */
class DepositRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deposit::class);
    }

    /**
     * Trouve tous les accomptes d'un devis
     *
     * @return Deposit[]
     */
    public function findByQuote(Quote $quote): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.quote = :quote')
            ->setParameter('quote', $quote)
            ->orderBy('d.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les accomptes payés d'un devis qui n'ont pas encore été déduits
     *
     * @return Deposit[]
     */
    public function findPaidNotDeducted(Quote $quote): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.quote = :quote')
            ->andWhere('d.status = :status')
            ->andWhere('d.invoice IS NULL')
            ->setParameter('quote', $quote)
            ->setParameter('status', DepositStatus::PAID)
            ->orderBy('d.paidAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les accomptes déduits sur une facture
     *
     * @return Deposit[]
     */
    public function findByInvoice(Invoice $invoice): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.invoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->orderBy('d.paidAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le total des accomptes payés pour un devis
     */
    public function getTotalPaidForQuote(Quote $quote): int
    {
        $result = $this->createQueryBuilder('d')
            ->select('SUM(d.amount)')
            ->andWhere('d.quote = :quote')
            ->andWhere('d.status = :status')
            ->setParameter('quote', $quote)
            ->setParameter('status', DepositStatus::PAID)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Calcule le total des accomptes déduits sur une facture
     */
    public function getTotalDeductedForInvoice(Invoice $invoice): int
    {
        $result = $this->createQueryBuilder('d')
            ->select('SUM(d.amount)')
            ->andWhere('d.invoice = :invoice')
            ->andWhere('d.deductedAt IS NOT NULL')
            ->setParameter('invoice', $invoice)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Trouve un accompte par son ID de session Stripe
     */
    public function findByStripeSessionId(string $sessionId): ?Deposit
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.stripeSessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les accomptes en attente (pour relance)
     *
     * @return Deposit[]
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status = :status')
            ->setParameter('status', DepositStatus::PENDING)
            ->orderBy('d.requestedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
