<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Quote;
use App\Service\QuoteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur API pour envoyer un devis (DRAFT → SENT)
 * 
 * Route: POST /api/quotes/{id}/send
 */
class QuoteSendController extends AbstractController
{
    public function __construct(
        private readonly QuoteService $quoteService,
    ) {
    }

    public function __invoke(Quote $quote, Request $request): JsonResponse
    {
        try {
            $this->quoteService->send($quote);

            return new JsonResponse([
                'success' => true,
                'message' => 'Devis envoyé avec succès',
                'quote' => [
                    'id' => $quote->getId(),
                    'numero' => $quote->getNumero(),
                    'statut' => $quote->getStatut()?->value,
                ],
            ], Response::HTTP_OK);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        } catch (\RuntimeException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}

