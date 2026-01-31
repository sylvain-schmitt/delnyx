<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\CompanySettingsRepository;
use App\Service\Google\GoogleCalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/google/calendar')]
class GoogleCalendarController extends AbstractController
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
        private readonly CompanySettingsRepository $companySettingsRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[Route('/connect', name: 'admin_google_calendar_connect')]
    public function connect(): Response
    {
        $settings = $this->companySettingsRepository->findOneBy([]);
        if (!$settings) {
            $this->addFlash('error', 'Paramètres de l\'entreprise introuvables.');
            return $this->redirectToRoute('admin_company_settings');
        }

        $authUrl = $this->googleCalendarService->generateAuthUrl($settings);

        if (!$authUrl) {
            $this->addFlash('error', 'Configuration Google Client ID ou Secret manquante.');
            return $this->redirectToRoute('admin_company_settings');
        }

        return $this->redirect($authUrl);
    }

    #[Route('/callback', name: 'admin_google_calendar_callback')]
    public function callback(Request $request): Response
    {
        $code = $request->query->get('code');
        $error = $request->query->get('error');

        if ($error) {
            $this->addFlash('error', 'Erreur d\'authentification Google : ' . $error);
            return $this->redirectToRoute('admin_company_settings');
        }

        if (!$code) {
            $this->addFlash('error', 'Code d\'authentification manquant.');
            return $this->redirectToRoute('admin_company_settings');
        }

        $settings = $this->companySettingsRepository->findOneBy([]);
        if (!$settings) {
            $this->addFlash('error', 'Paramètres de l\'entreprise introuvables.');
            return $this->redirectToRoute('admin_company_settings');
        }

        try {
            $this->googleCalendarService->authenticate($settings, $code);
            $this->addFlash('success', 'Synchronisation Google Calendar activée avec succès !');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'authentification : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_company_settings');
    }

    #[Route('/disconnect', name: 'admin_google_calendar_disconnect')]
    public function disconnect(): Response
    {
        $settings = $this->companySettingsRepository->findOneBy([]);
        if (!$settings) {
            return $this->redirectToRoute('admin_company_settings');
        }

        $settings->setGoogleOauthAccessToken(null);
        $settings->setGoogleOauthRefreshToken(null);
        $settings->setGoogleOauthTokenExpiresAt(null);

        $this->entityManager->flush();

        $this->addFlash('success', 'Synchronisation Google Calendar désactivée.');
        return $this->redirectToRoute('admin_company_settings');
    }
}
