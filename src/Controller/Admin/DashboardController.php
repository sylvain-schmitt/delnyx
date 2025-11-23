<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Quote;
use App\Entity\Invoice;
use App\Repository\ClientRepository;
use App\Repository\QuoteRepository;
use App\Repository\InvoiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private ClientRepository $clientRepository,
        private QuoteRepository $quoteRepository,
        private InvoiceRepository $invoiceRepository
    ) {}

    #[Route('/', name: 'dashboard')]
    public function index(): Response
    {
        // Statistiques générales
        $stats = [
            'clients' => $this->clientRepository->count([]),
            'quotes' => $this->quoteRepository->count([]),
            'invoices' => $this->invoiceRepository->count([]),
            'ca_mensuel' => $this->getCAMensuel(),
        ];

        // Calculs de croissance
        $growth = [
            'clients' => $this->getClientsGrowth(),
            'quotes' => $this->getQuotesGrowth(),
            'invoices' => $this->getInvoicesGrowth(),
            'ca' => $this->getCAGrowth(),
        ];

        // Quotes récents (5 derniers)
        $recent_quotes = $this->quoteRepository->findBy(
            [],
            ['dateCreation' => 'DESC'],
            5
        );

        // Invoices récentes (5 dernières)
        $recent_invoices = $this->invoiceRepository->findBy(
            [],
            ['dateCreation' => 'DESC'],
            5
        );

        return $this->render('admin/dashboard/index.html.twig', [
            'stats' => $stats,
            'growth' => $growth,
            'recent_quotes' => $recent_quotes,
            'recent_invoices' => $recent_invoices,
        ]);
    }

    /**
     * Calcule le CA mensuel
     */
    private function getCAMensuel(): int
    {
        $debutMois = new \DateTime('first day of this month');
        $finMois = new \DateTime('last day of this month');

        $invoices = $this->invoiceRepository->createQueryBuilder('i')
            ->where('i.dateCreation BETWEEN :debut AND :fin')
            ->andWhere('i.statut = :statut')
            ->setParameter('debut', $debutMois)
            ->setParameter('fin', $finMois)
            ->setParameter('statut', 'paid')
            ->getQuery()
            ->getResult();

        $ca = 0;
        foreach ($invoices as $invoice) {
            $ca += $invoice->getMontantTTC();
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
     * Calcule la croissance du nombre de quotes
     */
    private function getQuotesGrowth(): array
    {
        $debutMoisActuel = new \DateTime('first day of this month');
        $finMoisActuel = new \DateTime('last day of this month');

        $debutMoisPrecedent = (new \DateTime('first day of last month'));
        $finMoisPrecedent = (new \DateTime('last day of last month'));

        $quotesMoisActuel = $this->quoteRepository->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->where('q.dateCreation BETWEEN :debut AND :fin')
            ->setParameter('debut', $debutMoisActuel)
            ->setParameter('fin', $finMoisActuel)
            ->getQuery()
            ->getSingleScalarResult();

        $quotesMoisPrecedent = $this->quoteRepository->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->where('q.dateCreation BETWEEN :debut AND :fin')
            ->setParameter('debut', $debutMoisPrecedent)
            ->setParameter('fin', $finMoisPrecedent)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->calculateGrowth($quotesMoisActuel, $quotesMoisPrecedent);
    }

    /**
     * Calcule la croissance du nombre de invoices
     */
    private function getInvoicesGrowth(): array
    {
        $debutMoisActuel = new \DateTime('first day of this month');
        $finMoisActuel = new \DateTime('last day of this month');

        $debutMoisPrecedent = (new \DateTime('first day of last month'));
        $finMoisPrecedent = (new \DateTime('last day of last month'));

        $invoicesMoisActuel = $this->invoiceRepository->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.dateCreation BETWEEN :debut AND :fin')
            ->setParameter('debut', $debutMoisActuel)
            ->setParameter('fin', $finMoisActuel)
            ->getQuery()
            ->getSingleScalarResult();

        $invoicesMoisPrecedent = $this->invoiceRepository->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.dateCreation BETWEEN :debut AND :fin')
            ->setParameter('debut', $debutMoisPrecedent)
            ->setParameter('fin', $finMoisPrecedent)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->calculateGrowth($invoicesMoisActuel, $invoicesMoisPrecedent);
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
        $invoicesMoisActuel = $this->invoiceRepository->createQueryBuilder('i')
            ->where('i.dateCreation BETWEEN :debut AND :fin')
            ->andWhere('i.statut = :statut')
            ->setParameter('debut', $debutMoisActuel)
            ->setParameter('fin', $finMoisActuel)
            ->setParameter('statut', 'paid')
            ->getQuery()
            ->getResult();

        $caMoisActuel = 0;
        foreach ($invoicesMoisActuel as $invoice) {
            $caMoisActuel += $invoice->getMontantTTC();
        }

        // CA mois précédent
        $invoicesMoisPrecedent = $this->invoiceRepository->createQueryBuilder('i')
            ->where('i.dateCreation BETWEEN :debut AND :fin')
            ->andWhere('i.statut = :statut')
            ->setParameter('debut', $debutMoisPrecedent)
            ->setParameter('fin', $finMoisPrecedent)
            ->setParameter('statut', 'paid')
            ->getQuery()
            ->getResult();

        $caMoisPrecedent = 0;
        foreach ($invoicesMoisPrecedent as $invoice) {
            $caMoisPrecedent += $invoice->getMontantTTC();
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
