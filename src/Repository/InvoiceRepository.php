<?php

namespace App\Repository;

use App\Entity\Invoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    /**
     * Recherche des factures par terme (numéro ou nom client)
     *
     * @return Invoice[]
     */
    public function searchByTerm(string $term, int $limit = 5): array
    {
        $searchTerm = '%' . mb_strtolower(trim($term)) . '%';

        return $this->createQueryBuilder('i')
            ->leftJoin('i.client', 'c')
            ->where('LOWER(i.numero) LIKE :term')
            ->orWhere('LOWER(c.nom) LIKE :term')
            ->orWhere('LOWER(COALESCE(c.prenom, \'\')) LIKE :term')
            ->orWhere('LOWER(COALESCE(c.companyName, \'\')) LIKE :term')
            ->orWhere('LOWER(c.email) LIKE :term')
            ->orWhere('LOWER(CONCAT(COALESCE(c.prenom, \'\'), CONCAT(\' \', c.nom))) LIKE :term')
            ->orWhere('LOWER(CONCAT(c.nom, CONCAT(\' \', COALESCE(c.prenom, \'\')))) LIKE :term')
            ->setParameter('term', $searchTerm)
            ->orderBy('i.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche paginée des factures
     */
    public function createSearchQueryBuilder(string $term): \Doctrine\ORM\QueryBuilder
    {
        $searchTerm = '%' . mb_strtolower(trim($term)) . '%';

        return $this->createQueryBuilder('i')
            ->leftJoin('i.client', 'c')
            ->where('LOWER(i.numero) LIKE :term')
            ->orWhere('LOWER(c.nom) LIKE :term')
            ->orWhere('LOWER(COALESCE(c.prenom, \'\')) LIKE :term')
            ->orWhere('LOWER(COALESCE(c.companyName, \'\')) LIKE :term')
            ->orWhere('LOWER(c.email) LIKE :term')
            ->orWhere('LOWER(CONCAT(COALESCE(c.prenom, \'\'), CONCAT(\' \', c.nom))) LIKE :term')
            ->orWhere('LOWER(CONCAT(c.nom, CONCAT(\' \', COALESCE(c.prenom, \'\')))) LIKE :term')
            ->setParameter('term', $searchTerm)
            ->orderBy('i.dateCreation', 'DESC');
    }

    /**
     * Recherche avec pagination native (offset/limit)
     *
     * @return Invoice[]
     */
    public function searchByTermPaginated(string $term, int $limit = 20, int $offset = 0): array
    {
        return $this->createSearchQueryBuilder($term)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }
}
