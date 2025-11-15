<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Quote;
use App\Service\QuoteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôleur API pour accepter un devis (SENT → ACCEPTED)
 * 
 * Route: POST /api/quotes/{id}/accept
 */
class QuoteAcceptController extends AbstractController
{
    public function __construct(
        private readonly QuoteService $quoteService,
    ) {
    }

    public function __invoke(Quote $quote, Request $request): JsonResponse
    {
        try {
            $this->quoteService->accept($quote);

            return new JsonResponse([
                'success' => true,
                'message' => 'Devis accepté avec succès',
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

