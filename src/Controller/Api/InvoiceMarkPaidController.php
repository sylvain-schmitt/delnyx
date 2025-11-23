<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Invoice;
use App\Service\InvoiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur API pour marquer une facture comme payée
 * 
 * POST /api/invoices/{id}/mark-paid
 * Body: { "amount": 100.00 } (optionnel, null = paiement total)
 * 
 * @package App\Controller\Api
 */
#[Route('/api/invoices/{id}/mark-paid', name: 'api_invoice_mark_paid', methods: ['POST'])]
class InvoiceMarkPaidController extends AbstractController
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {
    }

    #[IsGranted('INVOICE_MARK_PAID', subject: 'invoice')]
    public function __invoke(Invoice $invoice, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $amount = isset($data['amount']) ? (float) $data['amount'] : null;

            $this->invoiceService->markPaid($invoice, $amount);

            return new JsonResponse([
                'status' => 'success',
                'message' => 'Facture marquée comme payée avec succès',
                'invoice' => [
                    'id' => $invoice->getId(),
                    'numero' => $invoice->getNumero(),
                    'statut' => $invoice->getStatutEnum()?->value,
                    'datePaiement' => $invoice->getDatePaiement()?->format('Y-m-d H:i:s'),
                ],
            ], Response::HTTP_OK);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        } catch (\RuntimeException $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}

