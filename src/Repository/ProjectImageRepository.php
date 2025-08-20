<?php

namespace App\Repository;

use App\Entity\ProjectImage;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectImage>
 */
class ProjectImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectImage::class);
    }

    /**
     * @return ProjectImage[] Returns an array of ProjectImage objects ordered by ordre
     */
    public function findByProjectOrdered(Project $project): array
    {
        return $this->createQueryBuilder('pi')
            ->andWhere('pi.projet = :project')
            ->setParameter('project', $project)
            ->orderBy('pi.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve la prochaine position d'ordre pour un projet
     */
    public function getNextOrderForProject(Project $project): int
    {
        $result = $this->createQueryBuilder('pi')
            ->select('MAX(pi.ordre)')
            ->andWhere('pi.projet = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? -1) + 1;
    }

    /**
     * Réorganise l'ordre des images d'un projet
     */
    public function reorderImages(Project $project, array $imageIds): void
    {
        $em = $this->getEntityManager();

        foreach ($imageIds as $index => $imageId) {
            $image = $this->find($imageId);
            if ($image && $image->getProjet() === $project) {
                $image->setOrdre($index);
                $em->persist($image);
            }
        }

        $em->flush();
    }

    /**
     * Trouve l'image principale d'un projet (première dans l'ordre)
     */
    public function findMainImageForProject(Project $project): ?ProjectImage
    {
        return $this->createQueryBuilder('pi')
            ->andWhere('pi.projet = :project')
            ->setParameter('project', $project)
            ->orderBy('pi.ordre', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Supprime toutes les images d'un projet
     */
    public function deleteAllForProject(Project $project): void
    {
        $this->createQueryBuilder('pi')
            ->delete()
            ->andWhere('pi.projet = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->execute();
    }
}
