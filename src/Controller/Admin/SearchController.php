<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ProjectRepository;
use App\Repository\QuoteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/admin')]
class SearchController extends AbstractController
{
    public function __construct(
        private readonly ClientRepository $clientRepository,
        private readonly QuoteRepository $quoteRepository,
        private readonly InvoiceRepository $invoiceRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * API de recherche rapide (dropdown)
     */
    #[Route('/search', name: 'admin_search', methods: ['GET'])]
    public function searchApi(Request $request): JsonResponse
    {
        $query = trim($request->query->get('q', ''));

        if (mb_strlen($query) < 2) {
            return $this->json(['results' => [], 'total' => 0]);
        }

        try {
            $clients = $this->clientRepository->searchByTerm($query, 5);
            $quotes = $this->quoteRepository->searchByTerm($query, 5);
            $invoices = $this->invoiceRepository->searchByTerm($query, 5);
            $projects = $this->projectRepository->searchByTerm($query, 5);

            $results = [
                'clients' => $this->formatClients($clients),
                'quotes' => $this->formatQuotes($quotes),
                'invoices' => $this->formatInvoices($invoices),
                'projects' => $this->formatProjects($projects),
            ];

            $total = count($results['clients']) + count($results['quotes'])
                + count($results['invoices']) + count($results['projects']);

            return $this->json([
                'results' => $results,
                'total' => $total,
                'query' => $query,
                'showAllUrl' => $this->urlGenerator->generate('admin_search_results', ['q' => $query]),
            ]);
        } catch (\Throwable $e) {
            // En cas d'erreur, retourner du JSON avec le message d'erreur
            return $this->json([
                'results' => ['clients' => [], 'quotes' => [], 'invoices' => [], 'projects' => []],
                'total' => 0,
                'query' => $query,
                'error' => $e->getMessage(),
                'showAllUrl' => $this->urlGenerator->generate('admin_search_results', ['q' => $query]),
            ], 200); // 200 pour éviter l'erreur frontend
        }
    }

    /**
     * Page de résultats paginée
     */
    #[Route('/search/results', name: 'admin_search_results', methods: ['GET'])]
    public function results(Request $request): Response
    {
        $query = trim($request->query->get('q', ''));
        $type = $request->query->get('type', 'all');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;

        if (mb_strlen($query) < 2) {
            return $this->render('admin/search/index.html.twig', [
                'query' => $query,
                'type' => $type,
                'results' => [],
                'items' => [],
                'current_page' => 1,
                'total_pages' => 1,
                'counts' => ['clients' => 0, 'quotes' => 0, 'invoices' => 0, 'projects' => 0],
            ]);
        }

        try {
            // Compter les résultats par type (limité à 1000 pour éviter les lenteurs)
            $counts = [
                'clients' => count($this->clientRepository->searchByTerm($query, 1000)),
                'quotes' => count($this->quoteRepository->searchByTerm($query, 1000)),
                'invoices' => count($this->invoiceRepository->searchByTerm($query, 1000)),
                'projects' => count($this->projectRepository->searchByTerm($query, 1000)),
            ];

            // Récupérer les résultats selon le type sélectionné
            $items = [];
            $totalItems = 0;
            $totalPages = 1;

            switch ($type) {
                case 'clients':
                    $totalItems = $counts['clients'];
                    $totalPages = max(1, (int) ceil($totalItems / $limit));
                    $page = min($page, $totalPages);
                    $items = $this->clientRepository->searchByTermPaginated($query, $limit, ($page - 1) * $limit);
                    break;
                case 'quotes':
                    $totalItems = $counts['quotes'];
                    $totalPages = max(1, (int) ceil($totalItems / $limit));
                    $page = min($page, $totalPages);
                    $items = $this->quoteRepository->searchByTermPaginated($query, $limit, ($page - 1) * $limit);
                    break;
                case 'invoices':
                    $totalItems = $counts['invoices'];
                    $totalPages = max(1, (int) ceil($totalItems / $limit));
                    $page = min($page, $totalPages);
                    $items = $this->invoiceRepository->searchByTermPaginated($query, $limit, ($page - 1) * $limit);
                    break;
                case 'projects':
                    $totalItems = $counts['projects'];
                    $totalPages = max(1, (int) ceil($totalItems / $limit));
                    $page = min($page, $totalPages);
                    $items = $this->projectRepository->searchByTermPaginated($query, $limit, ($page - 1) * $limit);
                    break;
                default:
                    // Tous les types - résultats groupés (limités à 10)
                    $items = [
                        'clients' => $this->clientRepository->searchByTerm($query, 10),
                        'quotes' => $this->quoteRepository->searchByTerm($query, 10),
                        'invoices' => $this->invoiceRepository->searchByTerm($query, 10),
                        'projects' => $this->projectRepository->searchByTerm($query, 10),
                    ];
                    break;
            }
        } catch (\Throwable $e) {
            // En cas d'erreur, afficher une page avec l'erreur
            $this->addFlash('error', 'Erreur de recherche : ' . $e->getMessage());
            $counts = ['clients' => 0, 'quotes' => 0, 'invoices' => 0, 'projects' => 0];
            $items = [];
            $totalPages = 1;
        }

        return $this->render('admin/search/index.html.twig', [
            'query' => $query,
            'type' => $type,
            'results' => $type === 'all' ? ($items ?? []) : [],
            'items' => $type !== 'all' ? ($items ?? []) : [],
            'current_page' => $page,
            'total_pages' => $totalPages,
            'counts' => $counts,
        ]);
    }

    /**
     * Formate les clients pour l'API JSON
     */
    private function formatClients(array $clients): array
    {
        return array_map(fn($client) => [
            'id' => $client->getId(),
            'title' => $client->getCompanyName() ?: ($client->getPrenom() . ' ' . $client->getNom()),
            'subtitle' => $client->getEmail(),
            'url' => $this->urlGenerator->generate('admin_client_edit', ['id' => $client->getId()]),
            'icon' => 'user',
        ], $clients);
    }

    /**
     * Formate les devis pour l'API JSON
     */
    private function formatQuotes(array $quotes): array
    {
        return array_map(fn($quote) => [
            'id' => $quote->getId(),
            'title' => 'Devis ' . $quote->getNumero(),
            'subtitle' => $quote->getClient()?->getCompanyName() ?: $quote->getClient()?->getNom(),
            'url' => $this->urlGenerator->generate('admin_quote_show', ['id' => $quote->getId()]),
            'icon' => 'file-text',
            'status' => $quote->getStatut()?->value,
        ], $quotes);
    }

    /**
     * Formate les factures pour l'API JSON
     */
    private function formatInvoices(array $invoices): array
    {
        return array_map(fn($invoice) => [
            'id' => $invoice->getId(),
            'title' => 'Facture ' . $invoice->getNumero(),
            'subtitle' => $invoice->getClient()?->getCompanyName() ?: $invoice->getClient()?->getNom(),
            'url' => $this->urlGenerator->generate('admin_invoice_show', ['id' => $invoice->getId()]),
            'icon' => 'receipt',
            'status' => $invoice->getStatut(),
        ], $invoices);
    }

    /**
     * Formate les projets pour l'API JSON
     */
    private function formatProjects(array $projects): array
    {
        return array_map(fn($project) => [
            'id' => $project->getId(),
            'title' => $project->getTitre(),
            'subtitle' => mb_substr($project->getDescription() ?? '', 0, 50) . '...',
            'url' => $this->urlGenerator->generate('admin_project_edit', ['id' => $project->getId()]),
            'icon' => 'folder',
        ], $projects);
    }
}
