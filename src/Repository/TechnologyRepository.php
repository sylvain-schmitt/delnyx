<?php

namespace App\Repository;

use App\Entity\Technology;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Technology>
 */
class TechnologyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Technology::class);
    }

    /**
     * @return Technology[] Returns an array of Technology objects
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les technologies les plus utilisées
     *
     * @return Technology[] Returns an array of Technology objects
     */
    public function findMostUsed(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.projets', 'p')
            ->where('p.statut = :statut')
            ->setParameter('statut', 'publie')
            ->groupBy('t.id')
            ->orderBy('COUNT(p.id)', 'DESC')
            ->addOrderBy('t.nom', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les technologies par couleur
     */
    public function findByColor(string $color): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.couleur = :color')
            ->setParameter('color', $color)
            ->orderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
