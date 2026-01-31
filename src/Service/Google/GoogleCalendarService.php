<?php

declare(strict_types=1);

namespace App\Service\Google;

use App\Entity\CompanySettings;
use App\Repository\CompanySettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Google\Client;
use Google\Service\Calendar;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GoogleCalendarService
{
    private Client $client;

    public function __construct(
        private readonly CompanySettingsRepository $companySettingsRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
        $this->client = new Client();
        $this->client->setScopes([Calendar::CALENDAR_READONLY, Calendar::CALENDAR_EVENTS]);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');
    }

    /**
     * Configure le client Google avec les credentials de l'entreprise
     */
    private function configureClient(CompanySettings $settings): bool
    {
        $clientId = $settings->getGoogleClientId();
        $clientSecret = $settings->getGoogleClientSecret();

        if (!$clientId || !$clientSecret) {
            return false;
        }

        $this->client->setClientId($clientId);
        $this->client->setClientSecret($clientSecret);

        // Permettre de forcer l'URL de redirection via une variable d'environnement (utile pour le local .local)
        $redirectUri = $_ENV['GOOGLE_REDIRECT_URI'] ?? $_SERVER['GOOGLE_REDIRECT_URI'] ?? null;
        if (!$redirectUri) {
            $redirectUri = $this->urlGenerator->generate('admin_google_calendar_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $this->client->setRedirectUri($redirectUri);

        return true;
    }

    /**
     * Génère l'URL d'authentification Google
     */
    public function generateAuthUrl(CompanySettings $settings): ?string
    {
        if (!$this->configureClient($settings)) {
            return null;
        }

        return $this->client->createAuthUrl();
    }

    /**
     * Échange le code d'autorisation contre des tokens
     */
    public function authenticate(CompanySettings $settings, string $code): void
    {
        if (!$this->configureClient($settings)) {
            throw new \RuntimeException('Google Client ID or Secret missing');
        }

        $token = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException('Error fetching access token: ' . $token['error_description']);
        }

        $this->saveToken($settings, $token);
    }

    /**
     * Sauvegarde les tokens dans l'entité CompanySettings
     */
    private function saveToken(CompanySettings $settings, array $token): void
    {
        $settings->setGoogleOauthAccessToken($token['access_token']);

        if (isset($token['refresh_token'])) {
            $settings->setGoogleOauthRefreshToken($token['refresh_token']);
        }

        $expiresAt = (new \DateTimeImmutable())->modify('+' . $token['expires_in'] . ' seconds');
        $settings->setGoogleOauthTokenExpiresAt($expiresAt);

        $this->entityManager->flush();
    }

    /**
     * Récupère un client authentifié (gère le refresh automatique)
     */
    public function getAuthenticatedClient(CompanySettings $settings): ?Client
    {
        if (!$this->configureClient($settings)) {
            return null;
        }

        $accessToken = $settings->getGoogleOauthAccessToken();
        if (!$accessToken) {
            return null;
        }

        $this->client->setAccessToken([
            'access_token' => $accessToken,
            'refresh_token' => $settings->getGoogleOauthRefreshToken(),
            'expires_in' => $settings->getGoogleOauthTokenExpiresAt() ? $settings->getGoogleOauthTokenExpiresAt()->getTimestamp() - time() : 0
        ]);

        if ($this->client->isAccessTokenExpired()) {
            $refreshToken = $settings->getGoogleOauthRefreshToken();
            if ($refreshToken) {
                $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
                if (isset($newToken['error'])) {
                    return null;
                }
                $this->saveToken($settings, $newToken);
            } else {
                return null;
            }
        }

        return $this->client;
    }

    /**
     * Liste les événements du calendrier
     */
    public function listEvents(CompanySettings $settings, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $client = $this->getAuthenticatedClient($settings);
        if (!$client) {
            return [];
        }

        $service = new Calendar($client);
        $calendarId = $settings->getGoogleCalendarId() ?: 'primary';

        $optParams = [
            'timeMin' => $start->format(\DateTimeInterface::RFC3339),
            'timeMax' => $end->format(\DateTimeInterface::RFC3339),
            'singleEvents' => true,
            'orderBy' => 'startTime',
        ];

        $results = $service->events->listEvents($calendarId, $optParams);
        return $results->getItems();
    }

    /**
     * Calcule les créneaux libres pour une période donnée
     */
    public function getFreeSlots(CompanySettings $settings, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $client = $this->getAuthenticatedClient($settings);
        if (!$client) {
            return [];
        }

        $workingHours = $settings->getWorkingHours();
        if (!$workingHours) {
            error_log("BOOKING ERROR: No working hours configured in CompanySettings.");
            return [];
        }

        // 1. Récupérer les slots occupés sur Google
        $events = $this->listEvents($settings, $start, $end);

        // 2. Récupérer les rendez-vous locaux (non annulés)
        $appointments = $this->entityManager->getRepository(\App\Entity\Appointment::class)
            ->findBetween($start, $end);

        $busyPeriods = [];
        foreach ($events as $event) {
            $eStart = $event->start->dateTime ?: $event->start->date;
            $eEnd = $event->end->dateTime ?: $event->end->date;
            $busyPeriods[] = [
                'start' => new \DateTime($eStart),
                'end' => new \DateTime($eEnd)
            ];
        }
        foreach ($appointments as $appt) {
            $busyPeriods[] = [
                'start' => \DateTime::createFromImmutable($appt->getStartAt()),
                'end' => \DateTime::createFromImmutable($appt->getEndAt())
            ];
        }

        // 3. Calculer les créneaux libres
        $freeSlots = [];
        $currentDate = $start instanceof \DateTime ? clone $start : new \DateTime($start->format('Y-m-d H:i:s'));

        while ($currentDate <= $end) {
            $dayName = strtolower($currentDate->format('l'));
            if (isset($workingHours[$dayName])) {
                foreach ($workingHours[$dayName] as $range) {
                    if (empty($range) || !str_contains($range, '-')) continue;
                    [$rStart, $rEnd] = explode('-', $range);

                    /** @var \DateTime $slotStart */
                    $slotStart = (clone $currentDate)->setTime((int)substr($rStart, 0, 2), (int)substr($rStart, 3, 2));
                    /** @var \DateTime $slotEnd */
                    $slotEnd = (clone $currentDate)->setTime((int)substr($rEnd, 0, 2), (int)substr($rEnd, 3, 2));

                    // On découpe par tranche de 30 min (à ajuster si besoin)
                    $interval = new \DateInterval('PT30M');
                    $tempStart = clone $slotStart;

                    while ($tempStart < $slotEnd) {
                        $tempEnd = (clone $tempStart)->add($interval);
                        if ($tempEnd > $slotEnd) break;

                        // Vérifier si busy
                        $isBusy = false;
                        foreach ($busyPeriods as $busy) {
                            if ($tempStart < $busy['end'] && $tempEnd > $busy['start']) {
                                $isBusy = true;
                                break;
                            }
                        }

                        if (!$isBusy) {
                            $freeSlots[] = [
                                'start' => clone $tempStart,
                                'end' => clone $tempEnd
                            ];
                        }

                        $tempStart = clone $tempEnd;
                    }
                }
            }
            /** @var \DateTime $currentDate */
            $currentDate->modify('+1 day')->setTime(0, 0);
        }

        error_log("BOOKING DEBUG: Found " . count($freeSlots) . " free slots for period " . $start->format('Y-m-d') . " to " . $end->format('Y-m-d'));
        return $freeSlots;
    }

    /**
     * Créé un événement sur Google Calendar
     */
    public function createEvent(\App\Entity\Appointment $appointment): ?string
    {
        $settings = $this->companySettingsRepository->findOneBy([]);
        $client = $this->getAuthenticatedClient($settings);
        if (!$client) {
            return null;
        }

        $service = new Calendar($client);
        $googleEvent = new \Google\Service\Calendar\Event([
            'summary' => $appointment->getSummary(),
            'description' => $appointment->getDescription() . "\n\nClient: " . $appointment->getClient()->getNomComplet(),
            'start' => [
                'dateTime' => $appointment->getStartAt()->format(\DateTimeInterface::RFC3339),
                'timeZone' => 'Europe/Paris',
            ],
            'end' => [
                'dateTime' => $appointment->getEndAt()->format(\DateTimeInterface::RFC3339),
                'timeZone' => 'Europe/Paris',
            ],
        ]);

        $calendarId = $settings->getGoogleCalendarId() ?: 'primary';
        $event = $service->events->insert($calendarId, $googleEvent);

        return $event->getId();
    }

    /**
     * Supprime un événement sur Google Calendar
     */
    public function deleteEvent(string $googleEventId): void
    {
        $settings = $this->companySettingsRepository->findOneBy([]);
        $client = $this->getAuthenticatedClient($settings);
        if (!$client) {
            return;
        }

        $service = new Calendar($client);
        $calendarId = $settings->getGoogleCalendarId() ?: 'primary';

        try {
            $service->events->delete($calendarId, $googleEventId);
        } catch (\Exception $e) {
            // Événement peut-être déjà supprimé
        }
    }
}
