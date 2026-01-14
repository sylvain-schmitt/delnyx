<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\PaymentStatus;
use App\Entity\Invoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * Récupère tous les paiements pour une facture
     *
     * @return Payment[]
     */
    public function findByInvoice(Invoice $invoice): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.invoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les paiements réussis pour une facture
     *
     * @return Payment[]
     */
    public function findSuccessfulByInvoice(Invoice $invoice): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.invoice = :invoice')
            ->andWhere('p.status = :status')
            ->setParameter('invoice', $invoice)
            ->setParameter('status', PaymentStatus::SUCCEEDED)
            ->orderBy('p.paidAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le total payé pour une facture (en centimes)
     */
    public function getTotalPaidForInvoice(Invoice $invoice): int
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->where('p.invoice = :invoice')
            ->andWhere('p.status = :status')
            ->setParameter('invoice', $invoice)
            ->setParameter('status', PaymentStatus::SUCCEEDED)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Trouve un paiement par son ID provider
     */
    public function findByProviderPaymentId(string $providerPaymentId): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->where('p.providerPaymentId = :id')
            ->setParameter('id', $providerPaymentId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
