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
 * Contrôleur API pour envoyer une facture
 * 
 * POST /api/invoices/{id}/send
 * Body (optionnel): { "channel": "email" | "pdp" | "both" }
 * 
 * @package App\Controller\Api
 */
#[Route('/api/invoices/{id}/send', name: 'api_invoice_send', methods: ['POST'])]
class InvoiceSendController extends AbstractController
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {
    }

    #[IsGranted('INVOICE_SEND', subject: 'invoice')]
    public function __invoke(Invoice $invoice, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $channel = $data['channel'] ?? 'email';

            $this->invoiceService->send($invoice, $channel);

            return new JsonResponse([
                'status' => 'success',
                'message' => 'Facture envoyée avec succès',
                'invoice' => [
                    'id' => $invoice->getId(),
                    'numero' => $invoice->getNumero(),
                    'dateEnvoi' => $invoice->getDateEnvoi()?->format('Y-m-d H:i:s'),
                    'sentCount' => $invoice->getSentCount(),
                    'deliveryChannel' => $invoice->getDeliveryChannel(),
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

