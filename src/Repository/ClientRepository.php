<?php

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * Recherche des clients par terme (nom, prénom, email, entreprise)
     *
     * @return Client[]
     */
    public function searchByTerm(string $term, int $limit = 5): array
    {
        $searchTerm = '%' . mb_strtolower(trim($term)) . '%';

        return $this->createQueryBuilder('c')
            ->where('LOWER(c.nom) LIKE :term')
            ->orWhere('LOWER(COALESCE(c.prenom, \'\')) LIKE :term')
            ->orWhere('LOWER(c.email) LIKE :term')
            ->orWhere('LOWER(COALESCE(c.companyName, \'\')) LIKE :term')
            ->orWhere('LOWER(CONCAT(COALESCE(c.prenom, \'\'), CONCAT(\' \', c.nom))) LIKE :term')
            ->orWhere('LOWER(CONCAT(c.nom, CONCAT(\' \', COALESCE(c.prenom, \'\')))) LIKE :term')
            ->setParameter('term', $searchTerm)
            ->orderBy('c.nom', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche paginée des clients
     */
    public function createSearchQueryBuilder(string $term): \Doctrine\ORM\QueryBuilder
    {
        $searchTerm = '%' . mb_strtolower(trim($term)) . '%';

        return $this->createQueryBuilder('c')
            ->where('LOWER(c.nom) LIKE :term')
            ->orWhere('LOWER(COALESCE(c.prenom, \'\')) LIKE :term')
            ->orWhere('LOWER(c.email) LIKE :term')
            ->orWhere('LOWER(COALESCE(c.companyName, \'\')) LIKE :term')
            ->orWhere('LOWER(CONCAT(COALESCE(c.prenom, \'\'), CONCAT(\' \', c.nom))) LIKE :term')
            ->orWhere('LOWER(CONCAT(c.nom, CONCAT(\' \', COALESCE(c.prenom, \'\')))) LIKE :term')
            ->setParameter('term', $searchTerm)
            ->orderBy('c.nom', 'ASC');
    }

    /**
     * Recherche avec pagination native (offset/limit)
     *
     * @return Client[]
     */
    public function searchByTermPaginated(string $term, int $limit = 20, int $offset = 0): array
    {
        return $this->createSearchQueryBuilder($term)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un client par terme de recherche (pour liens croisés)
     *
     * @return Client|null
     */
    public function findOneByTerm(string $term): ?Client
    {
        $results = $this->searchByTerm($term, 1);
        return $results[0] ?? null;
    }
}
