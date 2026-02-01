<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Quote;
use App\Entity\Invoice;
use App\Repository\ClientRepository;
use App\Repository\QuoteRepository;
use App\Repository\InvoiceRepository;
use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private ClientRepository $clientRepository,
        private QuoteRepository $quoteRepository,
        private InvoiceRepository $invoiceRepository,
        private DashboardService $dashboardService,
        private \App\Repository\CompanySettingsRepository $companySettingsRepository,
        private \App\Service\Google\GoogleCalendarService $googleCalendarService,
    ) {}

    #[Route('/', name: 'dashboard')]
    public function index(): Response
    {
        // Nouvelles statistiques centralisées
        $cardStats = $this->dashboardService->getStatsForCards();

        $stats = [
            'clients' => $cardStats['clients']['count'],
            'quotes' => $cardStats['quotes']['count'],
            'invoices' => $cardStats['invoices']['count'],
            'ca_mensuel' => $cardStats['ca']['total'],
        ];

        $growth = [
            'clients' => $cardStats['clients']['growth'],
            'quotes' => $cardStats['quotes']['growth'],
            'invoices' => $cardStats['invoices']['growth'],
            'ca' => $cardStats['ca']['growth'],
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

        // Nouvelles statistiques avancées
        $advancedStats = $this->dashboardService->getAllStats();
        $revenueChart = $this->dashboardService->createMonthlyRevenueChart();

        // Événements Google Calendar
        $googleEvents = [];
        $settings = $this->companySettingsRepository->findOneBy([]);
        if ($settings && $settings->isGoogleCalendarEnabled() && $settings->getGoogleOauthAccessToken()) {
            try {
                $start = new \DateTime('today 00:00:00');
                $end = new \DateTime('+7 days 23:59:59');
                $googleEvents = $this->googleCalendarService->listEvents($settings, $start, $end);
            } catch (\Exception $e) {
                // On log l'erreur mais on ne bloque pas le dashboard
                error_log("DASHBOARD Google Calendar error: " . $e->getMessage());
            }
        }

        return $this->render('admin/dashboard/index.html.twig', [
            'stats' => $stats,
            'growth' => $growth,
            'recent_quotes' => $recent_quotes,
            'recent_invoices' => $recent_invoices,
            'advanced_stats' => $advancedStats,
            'revenue_chart' => $revenueChart,
            'google_events' => $googleEvents,
        ]);
    }
}
