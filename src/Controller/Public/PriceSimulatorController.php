<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Repository\TariffRepository;
use App\Repository\CompanySettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PriceSimulatorController extends AbstractController
{
    public function __construct(
        private readonly string $aqualizeStripeProductId = '',
    ) {}

    #[Route('/simulateur', name: 'app_price_simulator')]
    public function index(TariffRepository $tariffRepository, CompanySettingsRepository $settingsRepository): Response
    {
        // Le simulateur s'adresse aux prospects de l'activité freelance : l'abonnement
        // Aqualize n'y a pas sa place — ce n'est pas une prestation qu'on leur vend. Il y
        // était pourtant affiché EN PREMIER (ordre 0).
        //
        // ⚠️ On exclut EXACTEMENT ce produit-là, par son identifiant Stripe.
        //
        // Un premier jet excluait tout tarif portant un stripeProductId, en supposant
        // qu'un identifiant Stripe signait un abonnement SaaS. C'était faux : en
        // production, les tarifs de site vitrine en portent aussi, et deux des trois ont
        // disparu du simulateur. Un critère « structurel » deviné sur les données de dév
        // est un critère faux — celui-ci ne peut viser que l'abonnement Aqualize.
        //
        // Si AQUALIZE_STRIPE_PRODUCT_ID n'est pas configuré, on n'exclut rien plutôt que
        // d'exclure au hasard.
        $tariffs = $this->tariffsProposables($tariffRepository);

        $settings = $settingsRepository->findOneBy([]);

        // Group tariffs by category
        $groupedTariffs = [];
        foreach ($tariffs as $tariff) {
            $groupedTariffs[$tariff->getCategorie()][] = $tariff;
        }

        return $this->render('home/simulator.html.twig', [
            'groupedTariffs' => $groupedTariffs,
            'tvaEnabled' => $settings ? $settings->isTvaEnabled() : false,
            'tvaRate' => $settings ? (float) $settings->getTauxTVADefaut() : 0,
        ]);
    }

    /**
     * @return \App\Entity\Tariff[] les prestations freelance, sans l'abonnement Aqualize
     */
    private function tariffsProposables(TariffRepository $repo): array
    {
        $tariffs = $repo->findBy(['actif' => true], ['ordre' => 'ASC']);

        if ($this->aqualizeStripeProductId === '') {
            return $tariffs;
        }

        return array_values(array_filter(
            $tariffs,
            fn ($t) => $t->getStripeProductId() !== $this->aqualizeStripeProductId,
        ));
    }
}
