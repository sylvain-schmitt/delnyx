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
#[Route('/public/checkout/aqualize/premium/{interval}', name: 'checkout_aqualize_premium', methods: ['GET', 'POST'])]
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

        // ⚠️ POST d'abord, query en REPLI.
        //
        // Aqualize envoyait l'e-mail et l'identifiant client dans l'URL : ils finissaient
        // donc dans les journaux du serveur, l'historique du navigateur et l'en-tête
        // `Referer`. Ils arrivent maintenant dans le corps d'un POST, qui ne va dans aucun
        // des trois.
        //
        // Le repli sur la query string n'est pas de la complaisance : il garde les anciens
        // liens fonctionnels le temps du déploiement — et surtout, Delnyx se déploie à la
        // main. Si Aqualize passait au POST avant que cette version-ci ne soit en ligne,
        // tous les achats tomberaient. À retirer une fois les deux déployés.
        $stripeCustomerId = $request->request->get('stripeCustomerId')
            ?: ($request->query->get('stripeCustomerId') ?: null);
        $email            = $request->request->get('email')
            ?: ($request->query->get('email') ?: null);

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
