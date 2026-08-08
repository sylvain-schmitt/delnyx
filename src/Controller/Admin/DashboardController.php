<?php

namespace App\Controller\Admin;

use App\Entity\Client;
use App\Entity\Quote;
use App\Entity\Invoice;
use App\Repository\ClientRepository;
use App\Repository\PurchaseInvoiceRepository;
use App\Repository\QuoteRepository;
use App\Repository\InvoiceRepository;
use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/admin', name: 'admin_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private ClientRepository $clientRepository,
        private QuoteRepository $quoteRepository,
        private InvoiceRepository $invoiceRepository,
        private PurchaseInvoiceRepository $purchaseInvoiceRepository,
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
            // Avoirs émis ce mois-ci : montant déduit du chiffre d'affaires, et nombre
            // de documents. Un CA net sans mention des avoirs qui l'ont réduit se lit mal.
            'avoirs_mensuel' => $cardStats['credit_notes']['total'],
            'avoirs_count'   => $cardStats['credit_notes']['count'],
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
        $monthlyPaidHistory = $this->dashboardService->getMonthlyPaidHistory();

        // Statistiques factures d'achats
        $user = $this->getUser();
        $companyId = null;
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
        }
        $purchaseStats = [
            'total'          => $companyId ? $this->purchaseInvoiceRepository->countForCompany($companyId) : 0,
            'monthly_total'  => $companyId ? $this->purchaseInvoiceRepository->sumTotalTTCForCompanyThisMonth($companyId) : 0.0,
            'pending'        => $companyId ? $this->purchaseInvoiceRepository->countPendingForCompany($companyId) : 0,
        ];

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
            'monthly_paid_history' => $monthlyPaidHistory,
            'google_events' => $googleEvents,
            'purchase_stats' => $purchaseStats,
        ]);
    }
}
