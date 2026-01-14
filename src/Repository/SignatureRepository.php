<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Signature;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Signature>
 */
class SignatureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Signature::class);
    }

    /**
     * Récupère toutes les signatures pour un document
     *
     * @param string $documentType Type de document (quote, amendment)
     * @param int $documentId ID du document
     * @return Signature[]
     */
    public function findByDocument(string $documentType, int $documentId): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.documentType = :type')
            ->andWhere('s.documentId = :id')
            ->setParameter('type', $documentType)
            ->setParameter('id', $documentId)
            ->orderBy('s.signedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un document a déjà été signé
     */
    public function isDocumentSigned(string $documentType, int $documentId): bool
    {
        $count = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.documentType = :type')
            ->andWhere('s.documentId = :id')
            ->setParameter('type', $documentType)
            ->setParameter('id', $documentId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
