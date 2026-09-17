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
    #[Route('/simulateur', name: 'app_price_simulator')]
    public function index(TariffRepository $tariffRepository, CompanySettingsRepository $settingsRepository): Response
    {
        // ⚠️ stripeProductId === null : on exclut les abonnements SaaS.
        //
        // Le simulateur s'adresse aux prospects de l'activité freelance ; l'abonnement
        // Aqualize n'a rien à y faire — ce n'est pas une prestation qu'on leur vend. Il
        // était pourtant affiché EN PREMIER (ordre 0).
        //
        // Le critère est structurel, pas un cas particulier nommé : un tarif porteur d'un
        // identifiant produit Stripe est un abonnement encaissé directement, pas une ligne
        // de devis. Aucun risque pour l'encaissement : AqualizeCheckoutController retrouve
        // le tarif par stripeProductId, sans jamais filtrer sur actif ni passer par ici.
        $tariffs = $tariffRepository->findBy(
            ['actif' => true, 'stripeProductId' => null],
            ['ordre' => 'ASC'],
        );
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
}
