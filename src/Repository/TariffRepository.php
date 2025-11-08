<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tariff;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité Tariff
 */
class TariffRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tariff::class);
    }

    /**
     * Retourne tous les tarifs actifs triés par catégorie et ordre
     */
    public function findActifsOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('t.categorie', 'ASC')
            ->addOrderBy('t.ordre', 'ASC')
            ->addOrderBy('t.prix', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les tarifs par catégorie
     */
    public function findByCategorie(string $categorie): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.categorie = :categorie')
            ->andWhere('t.actif = :actif')
            ->setParameter('categorie', $categorie)
            ->setParameter('actif', true)
            ->orderBy('t.ordre', 'ASC')
            ->addOrderBy('t.prix', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les tarifs pour les quotes (actifs et forfait uniquement)
     */
    public function findForQuotes(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.actif = :actif')
            ->andWhere('t.unite = :unite')
            ->setParameter('actif', true)
            ->setParameter('unite', 'forfait')
            ->orderBy('t.categorie', 'ASC')
            ->addOrderBy('t.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

