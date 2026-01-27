<?php

namespace App\Repository;

use App\Entity\Quote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quote>
 */
class QuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quote::class);
    }

    /**
     * Recherche des devis par terme (numéro ou nom client)
     *
     * @return Quote[]
     */
    public function searchByTerm(string $term, int $limit = 5): array
    {
        $searchTerm = '%' . mb_strtolower(trim($term)) . '%';

        return $this->createQueryBuilder('q')
            ->leftJoin('q.client', 'c')
            ->where('LOWER(q.numero) LIKE :term')
            ->orWhere('LOWER(c.nom) LIKE :term')
            ->orWhere('LOWER(COALESCE(c.prenom, \'\')) LIKE :term')
            ->orWhere('LOWER(COALESCE(c.companyName, \'\')) LIKE :term')
            ->orWhere('LOWER(c.email) LIKE :term')
            ->orWhere('LOWER(CONCAT(COALESCE(c.prenom, \'\'), CONCAT(\' \', c.nom))) LIKE :term')
            ->orWhere('LOWER(CONCAT(c.nom, CONCAT(\' \', COALESCE(c.prenom, \'\')))) LIKE :term')
            ->setParameter('term', $searchTerm)
            ->orderBy('q.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche paginée des devis
     */
    public function createSearchQueryBuilder(string $term): \Doctrine\ORM\QueryBuilder
    {
        $searchTerm = '%' . mb_strtolower(trim($term)) . '%';

        return $this->createQueryBuilder('q')
            ->leftJoin('q.client', 'c')
            ->where('LOWER(q.numero) LIKE :term')
            ->orWhere('LOWER(c.nom) LIKE :term')
            ->orWhere('LOWER(COALESCE(c.prenom, \'\')) LIKE :term')
            ->orWhere('LOWER(COALESCE(c.companyName, \'\')) LIKE :term')
            ->orWhere('LOWER(c.email) LIKE :term')
            ->orWhere('LOWER(CONCAT(COALESCE(c.prenom, \'\'), CONCAT(\' \', c.nom))) LIKE :term')
            ->orWhere('LOWER(CONCAT(c.nom, CONCAT(\' \', COALESCE(c.prenom, \'\')))) LIKE :term')
            ->setParameter('term', $searchTerm)
            ->orderBy('q.dateCreation', 'DESC');
    }

    /**
     * Recherche avec pagination native (offset/limit)
     *
     * @return Quote[]
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
