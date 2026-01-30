<?php

declare(strict_types=1);

namespace App\Service\Google;

use App\Repository\CompanySettingsRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;

/**
 * Service pour récupérer les avis Google Business via l'API Places.
 */
class GoogleReviewService
{
    private const API_URL = 'https://maps.googleapis.com/maps/api/place/details/json';
    private const CACHE_KEY = 'google_business_reviews';
    private const CACHE_TTL = 86400; // 24 heures

    public function __construct(
        private HttpClientInterface $httpClient,
        private CompanySettingsRepository $settingsRepository,
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {}

    /**
     * Récupère les avis Google mis en cache ou via l'API.
     */
    public function getLatestReviews(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->fetchFromApi();
        });
    }

    /**
     * Force le rafraîchissement du cache.
     */
    public function refreshCache(): array
    {
        $this->cache->delete(self::CACHE_KEY);
        return $this->getLatestReviews();
    }

    /**
     * Appel réel à l'API Google Places.
     */
    private function fetchFromApi(): array
    {
        $settings = $this->settingsRepository->findOneBy([]);

        if (!$settings || !$settings->hasValidGoogleReviewsConfig()) {
            $this->logger->warning('Google Reviews non configurés ou désactivés dans CompanySettings.');
            return [];
        }

        try {
            $response = $this->httpClient->request('GET', self::API_URL, [
                'query' => [
                    'place_id' => $settings->getGooglePlaceId(),
                    'key' => $settings->getGoogleApiKey(),
                    'fields' => 'reviews,rating,user_ratings_total',
                    'language' => 'fr',
                ],
            ]);

            if (200 !== $response->getStatusCode()) {
                $this->logger->error('Erreur API Google Places : Code ' . $response->getStatusCode());
                return [];
            }

            $data = $response->toArray();

            if (!isset($data['result']['reviews'])) {
                $this->logger->info('Aucun avis trouvé pour le Place ID fourni.');
                return [
                    'rating' => $data['result']['rating'] ?? 0,
                    'total_ratings' => $data['result']['user_ratings_total'] ?? 0,
                    'reviews' => []
                ];
            }

            return [
                'rating' => $data['result']['rating'] ?? 0,
                'total_ratings' => $data['result']['user_ratings_total'] ?? 0,
                'reviews' => $data['result']['reviews']
            ];
        } catch (\Exception $e) {
            $this->logger->error('Exception lors de la récupération des avis Google : ' . $e->getMessage());
            return [];
        }
    }
}
