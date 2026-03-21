<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Repository\TariffRepository;
use App\Service\StripeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Endpoint public — crée une Stripe Checkout Session pour l'abonnement Aqualize Premium.
 * Accessible sans authentification Delnyx (appelé depuis aqualize.local).
 */
#[Route('/public/checkout/aqualize/premium/{interval}', name: 'checkout_aqualize_premium', methods: ['GET'])]
class AqualizeCheckoutController extends AbstractController
{
    public function __construct(
        private readonly TariffRepository $tariffRepository,
        private readonly StripeService $stripeService,
        private readonly string $aqualizeStripeProductId,
        private readonly string $aqualizePublicUrl,
    ) {}

    public function __invoke(string $interval, Request $request): Response
    {
        if (!in_array($interval, ['monthly', 'yearly'], true)) {
            throw $this->createNotFoundException('Interval invalide. Utilisez "monthly" ou "yearly".');
        }

        if (empty($this->aqualizeStripeProductId)) {
            throw $this->createNotFoundException('AQUALIZE_STRIPE_PRODUCT_ID non configuré.');
        }

        $tariff = $this->tariffRepository->findOneBy(['stripeProductId' => $this->aqualizeStripeProductId]);
        if (!$tariff) {
            throw $this->createNotFoundException('Tariff Aqualize introuvable. Vérifiez AQUALIZE_STRIPE_PRODUCT_ID.');
        }

        $priceId = $interval === 'yearly'
            ? $tariff->getStripePriceIdYearly()
            : $tariff->getStripePriceIdMonthly();

        if (!$priceId) {
            throw $this->createNotFoundException(sprintf(
                'Prix %s non configuré sur le tariff "%s".',
                $interval,
                $tariff->getNom()
            ));
        }

        $stripeCustomerId = $request->query->get('stripeCustomerId') ?: null;
        $email            = $request->query->get('email') ?: null;

        $session = $this->stripeService->createCheckoutSession(
            priceId: $priceId,
            successUrl: rtrim($this->aqualizePublicUrl, '/') . '/premium?success=1',
            cancelUrl: rtrim($this->aqualizePublicUrl, '/') . '/premium',
            metadata: [
                'tariff_id' => (string) $tariff->getId(),
                'aqualize'  => 'true',
                'interval'  => $interval,
            ],
            stripeCustomerId: $stripeCustomerId,
            customerEmail: $email,
        );

        return new RedirectResponse($session->url);
    }
}
