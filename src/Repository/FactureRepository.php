<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Facture;
use App\Entity\FactureStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité Facture
 * 
 * @extends ServiceEntityRepository<Facture>
 */
class FactureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Facture::class);
    }

    /**
     * Trouve les factures par statut
     */
    public function findByStatut(FactureStatus $statut): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.statut = :statut')
            ->setParameter('statut', $statut->value)
            ->orderBy('f.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les factures en retard
     */
    public function findEnRetard(): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('f')
            ->andWhere('f.dateEcheance < :now')
            ->andWhere('f.statut != :payee')
            ->andWhere('f.statut != :annulee')
            ->setParameter('now', $now)
            ->setParameter('payee', FactureStatus::PAYEE->value)
            ->setParameter('annulee', FactureStatus::ANNULEE->value)
            ->orderBy('f.dateEcheance', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les factures par client
     */
    public function findByClient(int $clientId): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.client = :clientId')
            ->setParameter('clientId', $clientId)
            ->orderBy('f.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le chiffre d'affaires total
     */
    public function getChiffreAffairesTotal(): float
    {
        $result = $this->createQueryBuilder('f')
            ->select('SUM(f.montantTTC)')
            ->andWhere('f.statut = :payee')
            ->setParameter('payee', FactureStatus::PAYEE->value)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le chiffre d'affaires pour une période
     */
    public function getChiffreAffairesPeriode(\DateTime $debut, \DateTime $fin): float
    {
        $result = $this->createQueryBuilder('f')
            ->select('SUM(f.montantTTC)')
            ->andWhere('f.statut = :payee')
            ->andWhere('f.datePaiement >= :debut')
            ->andWhere('f.datePaiement <= :fin')
            ->setParameter('payee', FactureStatus::PAYEE->value)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Trouve les factures en attente de paiement
     */
    public function findEnAttentePaiement(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.statut = :envoyee')
            ->setParameter('envoyee', FactureStatus::ENVOYEE->value)
            ->orderBy('f.dateEcheance', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Génère le prochain numéro de facture
     */
    public function generateNextNumber(): string
    {
        $year = date('Y');
        $month = date('m');

        // Compter les factures de l'année en cours
        $count = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.numero LIKE :pattern')
            ->setParameter('pattern', "FAC-{$year}-{$month}-%")
            ->getQuery()
            ->getSingleScalarResult();

        $nextNumber = $count + 1;
        return sprintf('FAC-%s-%s-%03d', $year, $month, $nextNumber);
    }

    /**
     * Trouve les factures avec des montants restants
     */
    public function findAvecMontantRestant(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.montantAcompte IS NOT NULL')
            ->andWhere('f.montantAcompte < f.montantTTC')
            ->andWhere('f.statut != :payee')
            ->andWhere('f.statut != :annulee')
            ->setParameter('payee', FactureStatus::PAYEE->value)
            ->setParameter('annulee', FactureStatus::ANNULEE->value)
            ->orderBy('f.dateEcheance', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des factures par statut
     */
    public function getStatistiquesParStatut(): array
    {
        $qb = $this->createQueryBuilder('f')
            ->select('f.statut, COUNT(f.id) as count, SUM(f.montantTTC) as total')
            ->groupBy('f.statut');

        $results = $qb->getQuery()->getResult();

        $statistiques = [];
        foreach ($results as $result) {
            $statut = FactureStatus::from($result['statut']);
            $statistiques[$statut->value] = [
                'statut' => $statut,
                'count' => (int) $result['count'],
                'total' => (float) $result['total']
            ];
        }

        return $statistiques;
    }
}
