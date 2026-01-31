<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Appointment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appointment>
 */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    /**
     * @return Appointment[]
     */
    public function findBetween(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.startAt >= :start')
            ->andWhere('a.startAt <= :end')
            ->andWhere('a.status != :status')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', \App\Entity\AppointmentStatus::CANCELLED)
            ->orderBy('a.startAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
