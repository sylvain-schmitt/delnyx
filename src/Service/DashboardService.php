<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\InvoiceStatus;
use App\Entity\QuoteStatus;
use App\Repository\InvoiceRepository;
use App\Repository\QuoteRepository;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Service pour les statistiques du dashboard
 */
class DashboardService
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private QuoteRepository $quoteRepository,
        private ChartBuilderInterface $chartBuilder,
    ) {}

    /**
     * Récupère le CA par mois sur les 12 derniers mois
     *
     * @return array<string, mixed>
     */
    public function getMonthlyRevenue(): array
    {
        $data = [];
        $labels = [];

        // 12 derniers mois
        for ($i = 11; $i >= 0; $i--) {
            $date = new \DateTime("-{$i} months");
            $startOfMonth = (clone $date)->modify('first day of this month')->setTime(0, 0, 0);
            $endOfMonth = (clone $date)->modify('last day of this month')->setTime(23, 59, 59);

            $invoices = $this->invoiceRepository->createQueryBuilder('i')
                ->where('i.dateCreation BETWEEN :start AND :end')
                ->andWhere('i.statut = :statut')
                ->setParameter('start', $startOfMonth)
                ->setParameter('end', $endOfMonth)
                ->setParameter('statut', InvoiceStatus::PAID->value)
                ->getQuery()
                ->getResult();

            $monthTotal = 0.0;
            foreach ($invoices as $invoice) {
                $monthTotal += (float) $invoice->getMontantTTC();
            }

            $labels[] = $date->format('M Y');
            $data[] = $monthTotal;
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    /**
     * Crée le graphique Chart.js pour le CA mensuel
     */
    public function createMonthlyRevenueChart(): Chart
    {
        $monthlyData = $this->getMonthlyRevenue();

        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);
        $chart->setData([
            'labels' => $monthlyData['labels'],
            'datasets' => [
                [
                    'label' => 'Chiffre d\'affaires (€)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.2)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'data' => $monthlyData['data'],
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'labels' => [
                        'color' => 'rgba(255, 255, 255, 0.8)',
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'ticks' => [
                        'color' => 'rgba(255, 255, 255, 0.6)',
                    ],
                    'grid' => [
                        'color' => 'rgba(255, 255, 255, 0.1)',
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'color' => 'rgba(255, 255, 255, 0.6)',
                    ],
                    'grid' => [
                        'color' => 'rgba(255, 255, 255, 0.1)',
                    ],
                ],
            ],
        ]);

        return $chart;
    }

    /**
     * Récupère le CA annuel (année en cours)
     */
    public function getAnnualRevenue(): float
    {
        $startOfYear = new \DateTime('first day of January this year');
        $endOfYear = new \DateTime('last day of December this year');

        $invoices = $this->invoiceRepository->createQueryBuilder('i')
            ->where('i.dateCreation BETWEEN :start AND :end')
            ->andWhere('i.statut = :statut')
            ->setParameter('start', $startOfYear)
            ->setParameter('end', $endOfYear)
            ->setParameter('statut', InvoiceStatus::PAID->value)
            ->getQuery()
            ->getResult();

        $total = 0.0;
        foreach ($invoices as $invoice) {
            $total += (float) $invoice->getMontantTTC();
        }

        return $total;
    }

    /**
     * Récupère les factures impayées (émises mais non payées)
     *
     * @return array{total: float, count: int}
     */
    public function getUnpaidInvoices(): array
    {
        $statuts = [InvoiceStatus::ISSUED->value, InvoiceStatus::SENT->value];

        $invoices = $this->invoiceRepository->createQueryBuilder('i')
            ->where('i.statut IN (:statuts)')
            ->setParameter('statuts', $statuts)
            ->getQuery()
            ->getResult();

        $total = 0.0;
        foreach ($invoices as $invoice) {
            $total += (float) $invoice->getMontantTTC();
        }

        return [
            'total' => $total,
            'count' => count($invoices),
        ];
    }

    /**
     * Calcule le taux de conversion devis → facture
     *
     * @return array{rate: float, sent: int, signed: int}
     */
    public function getConversionRate(): array
    {
        // Devis envoyés (total)
        $sentQuotes = $this->quoteRepository->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->where('q.statut IN (:statuts)')
            ->setParameter('statuts', [
                QuoteStatus::SENT->value,
                QuoteStatus::SIGNED->value,
                QuoteStatus::REFUSED->value,
                QuoteStatus::EXPIRED->value,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        // Devis signés/acceptés
        $signedQuotes = $this->quoteRepository->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->where('q.statut IN (:statuts)')
            ->setParameter('statuts', [
                QuoteStatus::SIGNED->value,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        $rate = $sentQuotes > 0 ? ($signedQuotes / $sentQuotes) * 100 : 0;

        return [
            'rate' => round($rate, 1),
            'sent' => (int) $sentQuotes,
            'signed' => (int) $signedQuotes,
        ];
    }

    /**
     * Compare le CA de l'année en cours avec l'année précédente
     *
     * @return array{current_year: float, previous_year: float, growth: float, direction: string}
     */
    public function getYearlyComparison(): array
    {
        $currentYear = (int) date('Y');

        // CA année en cours
        $currentYearRevenue = $this->getRevenueForYear($currentYear);

        // CA année précédente
        $previousYearRevenue = $this->getRevenueForYear($currentYear - 1);

        // Calcul de la croissance
        $growth = 0.0;
        $direction = 'stable';

        if ($previousYearRevenue > 0) {
            $growth = (($currentYearRevenue - $previousYearRevenue) / $previousYearRevenue) * 100;
            $direction = $growth > 0.5 ? 'up' : ($growth < -0.5 ? 'down' : 'stable');
        } elseif ($currentYearRevenue > 0) {
            $growth = 100;
            $direction = 'up';
        }

        return [
            'current_year' => $currentYearRevenue,
            'previous_year' => $previousYearRevenue,
            'growth' => round(abs($growth), 1),
            'direction' => $direction,
        ];
    }

    /**
     * Récupère le CA pour une année donnée
     */
    private function getRevenueForYear(int $year): float
    {
        $startOfYear = new \DateTime("{$year}-01-01 00:00:00");
        $endOfYear = new \DateTime("{$year}-12-31 23:59:59");

        $invoices = $this->invoiceRepository->createQueryBuilder('i')
            ->where('i.dateCreation BETWEEN :start AND :end')
            ->andWhere('i.statut = :statut')
            ->setParameter('start', $startOfYear)
            ->setParameter('end', $endOfYear)
            ->setParameter('statut', InvoiceStatus::PAID->value)
            ->getQuery()
            ->getResult();

        $total = 0.0;
        foreach ($invoices as $invoice) {
            $total += (float) $invoice->getMontantTTC();
        }

        return $total;
    }

    /**
     * Récupère toutes les statistiques du dashboard
     *
     * @return array<string, mixed>
     */
    public function getAllStats(): array
    {
        return [
            'annual_revenue' => $this->getAnnualRevenue(),
            'unpaid_invoices' => $this->getUnpaidInvoices(),
            'conversion_rate' => $this->getConversionRate(),
            'yearly_comparison' => $this->getYearlyComparison(),
        ];
    }
}
