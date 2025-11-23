<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailLog>
 */
class EmailLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailLog::class);
    }

    /**
     * Récupère les emails envoyés pour une entité donnée
     */
    public function findByEntity(string $entityType, int $entityId): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.entityType = :type')
            ->andWhere('e.entityId = :id')
            ->setParameter('type', $entityType)
            ->setParameter('id', $entityId)
            ->orderBy('e.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les emails envoyés dans les dernières 24h
     */
    public function countRecentEmails(\DateTimeImmutable $since = null): int
    {
        $since = $since ?? new \DateTimeImmutable('-24 hours');
        
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.sentAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}


