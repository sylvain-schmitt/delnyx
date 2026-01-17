<?php

namespace App\Repository;

use App\Entity\Reminder;
use App\Entity\Invoice;
use App\Entity\ReminderRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reminder>
 */
class ReminderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reminder::class);
    }

    /**
     * Compte le nombre de relances envoyées pour une facture
     */
    public function countRemindersForInvoice(Invoice $invoice): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.invoice = :invoice')
            ->andWhere('r.status = :status')
            ->setParameter('invoice', $invoice)
            ->setParameter('status', Reminder::STATUS_SENT)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Vérifie si une relance a déjà été envoyée pour une facture et une règle
     */
    public function hasReminderBeenSent(Invoice $invoice, ReminderRule $rule): bool
    {
        $count = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.invoice = :invoice')
            ->andWhere('r.rule = :rule')
            ->andWhere('r.status = :status')
            ->setParameter('invoice', $invoice)
            ->setParameter('rule', $rule)
            ->setParameter('status', Reminder::STATUS_SENT)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Récupère l'historique des relances avec pagination
     *
     * @return Reminder[]
     */
    public function findRecentReminders(int $limit = 50, int $offset = 0, ?string $companyId = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.invoice', 'i')
            ->leftJoin('r.rule', 'ru')
            ->addSelect('i', 'ru')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($companyId !== null) {
            $qb->andWhere('i.companyId = :companyId')
                ->setParameter('companyId', $companyId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre total de relances
     */
    public function countAll(?string $companyId = null): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)');

        if ($companyId !== null) {
            $qb->leftJoin('r.invoice', 'i')
                ->andWhere('i.companyId = :companyId')
                ->setParameter('companyId', $companyId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
