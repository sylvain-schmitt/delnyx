<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Avenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité Avenant
 */
class AvenantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Avenant::class);
    }

    /**
     * Trouve les avenants pour un document spécifique
     */
    public function findByDocument(string $typeDocument, int $documentId): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.typeDocument = :typeDocument')
            ->andWhere('a.documentId = :documentId')
            ->setParameter('typeDocument', $typeDocument)
            ->setParameter('documentId', $documentId)
            ->orderBy('a.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les avenants validés pour un document
     */
    public function findValidatedByDocument(string $typeDocument, int $documentId): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.typeDocument = :typeDocument')
            ->andWhere('a.documentId = :documentId')
            ->andWhere('a.statut = :statut')
            ->setParameter('typeDocument', $typeDocument)
            ->setParameter('documentId', $documentId)
            ->setParameter('statut', 'valide')
            ->orderBy('a.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
