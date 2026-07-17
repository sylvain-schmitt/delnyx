<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Service\StripeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Endpoint serveur→serveur appelé par Aqualize (secret partagé X-Api-Secret) pour
 * appliquer un crédit Stripe "1 mois offert" au gagnant du concours Bac du mois.
 */
#[Route('/public/aqualize/contest-reward', name: 'aqualize_contest_reward', methods: ['POST'])]
class AqualizeContestRewardController extends AbstractController
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly string $aqualizeApiSecret,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = $request->headers->get('X-Api-Secret');
        if (!$secret || !hash_equals($this->aqualizeApiSecret, $secret)) {
            return $this->json(['success' => false, 'reason' => 'unauthorized'], 401);
        }

        $data       = json_decode($request->getContent(), true) ?? [];
        $customerId = $data['stripeCustomerId'] ?? null;
        $month      = $data['month'] ?? null;

        if (empty($customerId) || empty($month)) {
            return $this->json(['success' => false, 'reason' => 'missing_params'], 400);
        }

        try {
            $result = $this->stripeService->grantOneMonthCredit((string) $customerId, (string) $month);
        } catch (\Throwable $e) {
            // Erreur API Stripe transitoire → 500 pour qu'Aqualize réessaie
            return $this->json(['success' => false, 'reason' => 'stripe_error'], 500);
        }

        // Succès ou échec métier (pas d'abo, etc.) → 200 : non-retryable côté Aqualize
        return $this->json($result, 200);
    }
}
