<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Devis;
use App\Entity\Facture;
use App\Repository\ClientRepository;
use App\Repository\DevisRepository;
use App\Repository\FactureRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private ClientRepository $clientRepository,
        private DevisRepository $devisRepository,
        private FactureRepository $factureRepository
    ) {}

    #[Route('/', name: 'dashboard')]
    public function index(): Response
    {
        // Statistiques générales
        $stats = [
            'clients' => $this->clientRepository->count([]),
            'devis' => $this->devisRepository->count([]),
            'factures' => $this->factureRepository->count([]),
            'ca_mensuel' => $this->getCAMensuel(),
        ];

        // Calculs de croissance
        $growth = [
            'clients' => $this->getClientsGrowth(),
            'devis' => $this->getDevisGrowth(),
            'factures' => $this->getFacturesGrowth(),
            'ca' => $this->getCAGrowth(),
        ];

        // Devis récents (5 derniers)
        $recent_devis = $this->devisRepository->findBy(
            [],
            ['dateCreation' => 'DESC'],
            5
        );

        // Factures récentes (5 dernières)
        $recent_factures = $this->factureRepository->findBy(
            [],
            ['dateCreation' => 'DESC'],
            5
        );

        return $this->render('admin/dashboard/index.html.twig', [
            'stats' => $stats,
            'growth' => $growth,
            'recent_devis' => $recent_devis,
            'recent_factures' => $recent_factures,
        ]);
    }

    /**
     * Calcule le CA mensuel
     */
    private function getCAMensuel(): int
    {
        $debutMois = new \DateTime('first day of this month');
        $finMois = new \DateTime('last day of this month');

        $factures = $this->factureRepository->createQueryBuilder('f')
            ->where('f.dateCreation BETWEEN :debut AND :fin')
            ->andWhere('f.statut = :statut')
            ->setParameter('debut', $debutMois)
            ->setParameter('fin', $finMois)
            ->setParameter('statut', 'payee')
            ->getQuery()
            ->getResult();

        $ca = 0;
        foreach ($factures as $facture) {
            $ca += $facture->getMontantTTC();
        }

        return $ca;
    }

    /**
     * Calcule la croissance du nombre de clients (mois actuel vs mois précédent)
     */
    private function getClientsGrowth(): array
    {
        $debutMoisActuel = new \DateTime('first day of this month');
        $finMoisActuel = new \DateTime('last day of this month');

        $debutMoisPrecedent = (new \DateTime('first day of last month'));
        $finMoisPrecedent = (new \DateTime('last day of last month'));

        $clientsMoisActuel = $this->clientRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.dateCreation BETWEEN :debut AND :fin')
            ->setParameter('debut', $debutMoisActuel)
            ->setParameter('fin', $finMoisActuel)
            ->getQuery()
            ->getSingleScalarResult();

        $clientsMoisPrecedent = $this->clientRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.dateCreation BETWEEN :debut AND :fin')
            ->setParameter('debut', $debutMoisPrecedent)
            ->setParameter('fin', $finMoisPrecedent)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->calculateGrowth($clientsMoisActuel, $clientsMoisPrecedent);
    }

    /**
     * Calcule la croissance du nombre de devis
     */
    private function getDevisGrowth(): array
    {
        $debutMoisActuel = new \DateTime('first day of this month');
        $finMoisActuel = new \DateTime('last day of this month');

        $debutMoisPrecedent = (new \DateTime('first day of last month'));
        $finMoisPrecedent = (new \DateTime('last day of last month'));

        $devisMoisActuel = $this->devisRepository->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.dateCreation BETWEEN :debut AND :fin')
            ->setParameter('debut', $debutMoisActuel)
            ->setParameter('fin', $finMoisActuel)
            ->getQuery()
            ->getSingleScalarResult();

        $devisMoisPrecedent = $this->devisRepository->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.dateCreation BETWEEN :debut AND :fin')
            ->setParameter('debut', $debutMoisPrecedent)
            ->setParameter('fin', $finMoisPrecedent)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->calculateGrowth($devisMoisActuel, $devisMoisPrecedent);
    }

    /**
     * Calcule la croissance du nombre de factures
     */
    private function getFacturesGrowth(): array
    {
        $debutMoisActuel = new \DateTime('first day of this month');
        $finMoisActuel = new \DateTime('last day of this month');

        $debutMoisPrecedent = (new \DateTime('first day of last month'));
        $finMoisPrecedent = (new \DateTime('last day of last month'));

        $facturesMoisActuel = $this->factureRepository->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.dateCreation BETWEEN :debut AND :fin')
            ->setParameter('debut', $debutMoisActuel)
            ->setParameter('fin', $finMoisActuel)
            ->getQuery()
            ->getSingleScalarResult();

        $facturesMoisPrecedent = $this->factureRepository->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.dateCreation BETWEEN :debut AND :fin')
            ->setParameter('debut', $debutMoisPrecedent)
            ->setParameter('fin', $finMoisPrecedent)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->calculateGrowth($facturesMoisActuel, $facturesMoisPrecedent);
    }

    /**
     * Calcule la croissance du CA
     */
    private function getCAGrowth(): array
    {
        $debutMoisActuel = new \DateTime('first day of this month');
        $finMoisActuel = new \DateTime('last day of this month');

        $debutMoisPrecedent = (new \DateTime('first day of last month'));
        $finMoisPrecedent = (new \DateTime('last day of last month'));

        // CA mois actuel
        $facturesMoisActuel = $this->factureRepository->createQueryBuilder('f')
            ->where('f.dateCreation BETWEEN :debut AND :fin')
            ->andWhere('f.statut = :statut')
            ->setParameter('debut', $debutMoisActuel)
            ->setParameter('fin', $finMoisActuel)
            ->setParameter('statut', 'payee')
            ->getQuery()
            ->getResult();

        $caMoisActuel = 0;
        foreach ($facturesMoisActuel as $facture) {
            $caMoisActuel += $facture->getMontantTTC();
        }

        // CA mois précédent
        $facturesMoisPrecedent = $this->factureRepository->createQueryBuilder('f')
            ->where('f.dateCreation BETWEEN :debut AND :fin')
            ->andWhere('f.statut = :statut')
            ->setParameter('debut', $debutMoisPrecedent)
            ->setParameter('fin', $finMoisPrecedent)
            ->setParameter('statut', 'payee')
            ->getQuery()
            ->getResult();

        $caMoisPrecedent = 0;
        foreach ($facturesMoisPrecedent as $facture) {
            $caMoisPrecedent += $facture->getMontantTTC();
        }

        return $this->calculateGrowth($caMoisActuel, $caMoisPrecedent);
    }

    /**
     * Calcule le pourcentage de croissance et détermine la direction
     * 
     * @return array ['percentage' => float, 'direction' => 'up'|'down'|'stable']
     */
    private function calculateGrowth(float $current, float $previous): array
    {
        // Si les deux sont à 0, c'est stable
        if ($current == 0 && $previous == 0) {
            return ['percentage' => 0, 'direction' => 'stable'];
        }

        // Si le précédent est à 0 mais pas l'actuel, croissance de 100%
        if ($previous == 0 && $current > 0) {
            return ['percentage' => 100, 'direction' => 'up'];
        }

        // Si l'actuel est à 0 mais pas le précédent, décroissance de 100%
        if ($current == 0 && $previous > 0) {
            return ['percentage' => 100, 'direction' => 'down'];
        }

        // Calcul normal du pourcentage
        $percentage = (($current - $previous) / $previous) * 100;

        $direction = 'stable';
        if ($percentage > 0.5) {
            $direction = 'up';
        } elseif ($percentage < -0.5) {
            $direction = 'down';
        }

        return [
            'percentage' => round(abs($percentage), 1),
            'direction' => $direction
        ];
    }
}
