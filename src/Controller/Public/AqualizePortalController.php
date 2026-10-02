<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Service\StripeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Endpoint public — génère une Stripe Billing Portal Session pour la gestion d'abonnement.
 * Accessible sans authentification Delnyx (appelé depuis aqualize).
 */
#[Route('/public/subscription/portal', name: 'subscription_portal_aqualize', methods: ['GET', 'POST'])]
class AqualizePortalController extends AbstractController
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly string $aqualizePublicUrl,
    ) {}

    public function __invoke(Request $request): Response
    {
        // POST d'abord, query en repli — même raison que le checkout : l'identifiant
        // client ne doit pas s'inscrire dans les journaux ni dans l'historique. Le repli
        // garde les anciens liens vivants le temps du déploiement manuel de Delnyx.
        $stripeCustomerId = $request->request->get('stripeCustomerId')
            ?: $request->query->get('stripeCustomerId');

        if (empty($stripeCustomerId)) {
            throw $this->createNotFoundException('stripeCustomerId manquant.');
        }

        $returnPath = $request->query->get('returnPath', '/parametres');
        // Sécurité : on accepte uniquement les chemins internes (pas de redirect externe)
        if (!str_starts_with($returnPath, '/') || str_contains($returnPath, '//')) {
            $returnPath = '/parametres';
        }
        $returnUrl = rtrim($this->aqualizePublicUrl, '/') . $returnPath;

        try {
            $portalUrl = $this->stripeService->createBillingPortalSession(
                stripeCustomerId: $stripeCustomerId,
                returnUrl: $returnUrl,
            );
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Impossible de créer la session portail Stripe : ' . $e->getMessage());
        }

        return new RedirectResponse($portalUrl);
    }
}
