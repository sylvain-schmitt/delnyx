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
 * Contrôleur API pour signer un devis (SENT/ACCEPTED → SIGNED)
 * 
 * Route: POST /api/quotes/{id}/sign
 * 
 * Body (optionnel):
 * {
 *   "signatureClient": "signature_base64..."
 * }
 */
class QuoteSignController extends AbstractController
{
    public function __construct(
        private readonly QuoteService $quoteService,
    ) {
    }

    public function __invoke(Quote $quote, Request $request): JsonResponse
    {
        try {
            // Récupérer la signature du client si fournie
            $data = json_decode($request->getContent(), true);
            $signatureClient = $data['signatureClient'] ?? null;

            $this->quoteService->sign($quote, $signatureClient);

            return new JsonResponse([
                'success' => true,
                'message' => 'Devis signé avec succès - CONTRAT créé',
                'quote' => [
                    'id' => $quote->getId(),
                    'numero' => $quote->getNumero(),
                    'statut' => $quote->getStatut()?->value,
                    'dateSignature' => $quote->getDateSignature()?->format('Y-m-d H:i:s'),
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

