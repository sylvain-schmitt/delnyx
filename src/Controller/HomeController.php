<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        \App\Service\Google\GoogleReviewService $googleReviewService,
        \App\Repository\CompanySettingsRepository $settingsRepository
    ): Response {
        return $this->render('home/index.html.twig', [
            'googleReviews' => $googleReviewService->getLatestReviews(),
            'companySettings' => $settingsRepository->findOneBy([])
        ]);
    }

    #[Route('/about', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('home/about.html.twig');
    }

    #[Route('/services', name: 'app_services')]
    public function services(): Response
    {
        return $this->render('home/services.html.twig');
    }
}
