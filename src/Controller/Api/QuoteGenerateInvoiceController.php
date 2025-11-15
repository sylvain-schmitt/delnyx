<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Quote;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôleur API pour générer une facture depuis un devis signé
 * 
 * Route: POST /api/quotes/{id}/generate-invoice
 * 
 * TODO: Implémenter la génération de facture via InvoiceService
 */
class QuoteGenerateInvoiceController extends AbstractController
{
    public function __invoke(Quote $quote, Request $request): JsonResponse
    {
        // TODO: Implémenter InvoiceService->createFromQuote($quote)
        // Pour l'instant, retourner une erreur indiquant que c'est à implémenter
        
        return new JsonResponse([
            'success' => false,
            'message' => 'La génération de facture depuis un devis n\'est pas encore implémentée.',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }
}

