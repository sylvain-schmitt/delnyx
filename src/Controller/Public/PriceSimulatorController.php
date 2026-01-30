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
        $tariffs = $tariffRepository->findBy(['actif' => true], ['ordre' => 'ASC']);
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
