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
 * Contrôleur API pour émettre une facture
 * 
 * POST /api/invoices/{id}/issue
 * 
 * @package App\Controller\Api
 */
#[Route('/api/invoices/{id}/issue', name: 'api_invoice_issue', methods: ['POST'])]
class InvoiceIssueController extends AbstractController
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {
    }

    #[IsGranted('INVOICE_ISSUE', subject: 'invoice')]
    public function __invoke(Invoice $invoice, Request $request): JsonResponse
    {
        try {
            $this->invoiceService->issue($invoice);

            return new JsonResponse([
                'status' => 'success',
                'message' => 'Facture émise avec succès',
                'invoice' => [
                    'id' => $invoice->getId(),
                    'numero' => $invoice->getNumero(),
                    'statut' => $invoice->getStatutEnum()?->value,
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

