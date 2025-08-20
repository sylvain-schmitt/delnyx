<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\Technology;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * @return Project[] Returns an array of published Project objects
     */
    public function findPublishedProjects(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.statut = :statut')
            ->setParameter('statut', Project::STATUT_PUBLIE)
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les projets publiés avec leurs technologies et images
     *
     * @return Project[] Returns an array of Project objects
     */
    public function findPublishedWithDetails(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.technologies', 't')
            ->leftJoin('p.images', 'i')
            ->addSelect('t', 'i')
            ->andWhere('p.statut = :statut')
            ->setParameter('statut', Project::STATUT_PUBLIE)
            ->orderBy('p.dateCreation', 'DESC')
            ->addOrderBy('i.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les projets par technologie
     *
     * @return Project[] Returns an array of Project objects
     */
    public function findByTechnology(Technology $technology): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.technologies', 't')
            ->andWhere('t = :technology')
            ->andWhere('p.statut = :statut')
            ->setParameter('technology', $technology)
            ->setParameter('statut', Project::STATUT_PUBLIE)
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de projets par terme
     *
     * @return Project[] Returns an array of Project objects
     */
    public function search(string $term): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.technologies', 't')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.titre LIKE :term OR p.description LIKE :term OR t.nom LIKE :term')
            ->setParameter('statut', Project::STATUT_PUBLIE)
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de projets publiés
     */
    public function countPublished(): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.statut = :statut')
            ->setParameter('statut', Project::STATUT_PUBLIE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les projets récents (derniers 30 jours)
     *
     * @return Project[] Returns an array of Project objects
     */
    public function findRecent(int $days = 30): array
    {
        $date = new \DateTimeImmutable('-' . $days . ' days');

        return $this->createQueryBuilder('p')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.dateCreation >= :date')
            ->setParameter('statut', Project::STATUT_PUBLIE)
            ->setParameter('date', $date)
            ->orderBy('p.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
