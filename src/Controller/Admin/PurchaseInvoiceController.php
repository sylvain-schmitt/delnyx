<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Message\SyncPurchaseInvoicesMessage;
use App\Repository\CompanySettingsRepository;
use App\Repository\PurchaseInvoiceRepository;
use App\Service\PdfGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/achats', name: 'admin_purchase_invoice_')]
#[IsGranted('ROLE_USER')]
class PurchaseInvoiceController extends AbstractController
{
    public function __construct(
        private PurchaseInvoiceRepository $purchaseInvoiceRepository,
        private CompanySettingsRepository $companySettingsRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private PdfGeneratorService $pdfGeneratorService,
    ) {}

    private function getCompanyId(): ?string
    {
        $user = $this->getUser();
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            return Uuid::v5($namespace, $user->getEmail())->toString();
        }
        return null;
    }

    #[Route('/', name: 'index')]
    public function index(Request $request): Response
    {
        $companyId = $this->getCompanyId();
        $page      = max(1, $request->query->getInt('page', 1));
        $limit     = 15;

        $qb = $this->purchaseInvoiceRepository->createQueryBuilder('p');
        if ($companyId) {
            $qb->where('p.companyId = :companyId')->setParameter('companyId', $companyId);
        }
        $qb->orderBy('p.createdAt', 'DESC');

        $countQb = (clone $qb)->select('COUNT(p.id)')->resetDQLPart('orderBy');
        $total   = (int) $countQb->getQuery()->getSingleScalarResult();
        $totalPages = max(1, (int) ceil($total / $limit));
        $page       = min($page, $totalPages);

        $invoices = $qb->select('p')
            ->setMaxResults($limit)
            ->setFirstResult(($page - 1) * $limit)
            ->getQuery()
            ->getResult();

        return $this->render('admin/purchase_invoice/index.html.twig', [
            'invoices'     => $invoices,
            'current_page' => $page,
            'total_pages'  => $totalPages,
            'total'        => $total,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $companyId = $this->getCompanyId();
        $invoice   = $this->purchaseInvoiceRepository->find($id);

        if (!$invoice || ($companyId && $invoice->getCompanyId() !== $companyId)) {
            $this->addFlash('error', 'Facture d\'achat introuvable.');
            return $this->redirectToRoute('admin_purchase_invoice_index');
        }

        return $this->render('admin/purchase_invoice/show.html.twig', [
            'invoice' => $invoice,
        ]);
    }

    #[Route('/{id}/pdf', name: 'pdf', requirements: ['id' => '\d+'])]
    public function pdf(int $id): Response
    {
        $companyId = $this->getCompanyId();
        $invoice   = $this->purchaseInvoiceRepository->find($id);

        if (!$invoice || ($companyId && $invoice->getCompanyId() !== $companyId)) {
            throw $this->createNotFoundException();
        }

        $detail = json_decode($invoice->getPdpRawResponse() ?? '{}', true);
        $en     = $detail['en_invoice'] ?? [];

        return $this->pdfGeneratorService->generatePdf('pdf/achat.html.twig', [
            'invoice'  => $invoice,
            'en'       => $en,
            'filename' => 'achat-' . ($invoice->getInvoiceNumber() ?? $invoice->getPdpInvoiceId()),
        ]);
    }

    #[Route('/sync', name: 'sync', methods: ['POST'])]
    public function sync(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('purchase_invoice_sync', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_purchase_invoice_index');
        }

        $companyId = $this->getCompanyId();
        if (!$companyId) {
            $this->addFlash('error', 'Utilisateur non authentifié.');
            return $this->redirectToRoute('admin_purchase_invoice_index');
        }

        $this->messageBus->dispatch(new SyncPurchaseInvoicesMessage($companyId));
        $this->addFlash('success', 'Synchronisation des factures reçues lancée — résultat disponible dans quelques instants.');

        return $this->redirectToRoute('admin_purchase_invoice_index');
    }

    #[Route('/{id}/acknowledge', name: 'acknowledge', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function acknowledge(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('purchase_invoice_acknowledge_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_purchase_invoice_show', ['id' => $id]);
        }

        $companyId = $this->getCompanyId();
        $invoice   = $this->purchaseInvoiceRepository->find($id);

        if (!$invoice || ($companyId && $invoice->getCompanyId() !== $companyId)) {
            $this->addFlash('error', 'Facture d\'achat introuvable.');
            return $this->redirectToRoute('admin_purchase_invoice_index');
        }

        $invoice->setLocalStatus('acknowledged');
        $invoice->setAcknowledgedAt(new \DateTime());
        $invoice->setRejectedAt(null);
        $invoice->setRejectionReason(null);
        $this->entityManager->flush();

        $this->addFlash('success', 'Facture d\'achat acceptée.');
        return $this->redirectToRoute('admin_purchase_invoice_show', ['id' => $id]);
    }

    #[Route('/{id}/mark-paid', name: 'mark_paid', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markPaid(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('purchase_invoice_mark_paid_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_purchase_invoice_show', ['id' => $id]);
        }

        $companyId = $this->getCompanyId();
        $invoice   = $this->purchaseInvoiceRepository->find($id);

        if (!$invoice || ($companyId && $invoice->getCompanyId() !== $companyId)) {
            $this->addFlash('error', 'Facture d\'achat introuvable.');
            return $this->redirectToRoute('admin_purchase_invoice_index');
        }

        $invoice->setLocalStatus('paid');
        $this->entityManager->flush();

        $this->addFlash('success', 'Facture marquée comme payée.');
        return $this->redirectToRoute('admin_purchase_invoice_show', ['id' => $id]);
    }

    #[Route('/{id}/reject', name: 'reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reject(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('purchase_invoice_reject_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_purchase_invoice_show', ['id' => $id]);
        }

        $companyId = $this->getCompanyId();
        $invoice   = $this->purchaseInvoiceRepository->find($id);

        if (!$invoice || ($companyId && $invoice->getCompanyId() !== $companyId)) {
            $this->addFlash('error', 'Facture d\'achat introuvable.');
            return $this->redirectToRoute('admin_purchase_invoice_index');
        }

        $invoice->setLocalStatus('rejected');
        $invoice->setRejectedAt(new \DateTime());
        $invoice->setRejectionReason($request->request->get('reason'));
        $invoice->setAcknowledgedAt(null);
        $this->entityManager->flush();

        $this->addFlash('success', 'Facture d\'achat rejetée.');
        return $this->redirectToRoute('admin_purchase_invoice_show', ['id' => $id]);
    }
}
