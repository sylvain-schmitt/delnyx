<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AqualizeNotifierService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $aqualizeApiUrl,
        private readonly string $aqualizeApiSecret,
    ) {}

    /**
     * Notifie Aqualize du changement de plan d'un utilisateur.
     * Best-effort : ne lève jamais d'exception pour ne pas bloquer le webhook.
     */
    public function notifyPlanUpdate(string $email, string $plan, string $stripeCustomerId, ?\DateTimeInterface $cancelAt = null): void
    {
        if (empty($email) || empty($this->aqualizeApiUrl) || empty($this->aqualizeApiSecret)) {
            $this->logger->warning('AqualizeNotifier: configuration incomplète, notification ignorée', [
                'email' => $email,
                'plan'  => $plan,
            ]);
            return;
        }

        $payload = [
            'email'            => $email,
            'plan'             => $plan,
            'stripeCustomerId' => $stripeCustomerId,
        ];
        if ($cancelAt !== null) {
            $payload['cancelAt'] = $cancelAt->format(\DateTimeInterface::ATOM);
        }

        try {
            $response = $this->httpClient->request('POST', $this->aqualizeApiUrl . '/api/subscription/update', [
                'headers' => [
                    'X-Api-Secret' => $this->aqualizeApiSecret,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'verify_peer' => false, // self-signed cert en local dev
                'verify_host' => false,
                'timeout'     => 5,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 200) {
                $this->logger->info('Aqualize notifié du changement de plan', [
                    'email'  => $email,
                    'plan'   => $plan,
                ]);
            } else {
                $this->logger->warning('Aqualize a répondu avec un code inattendu', [
                    'email'  => $email,
                    'plan'   => $plan,
                    'status' => $statusCode,
                    'body'   => $response->getContent(false),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('AqualizeNotifier: erreur HTTP', [
                'email' => $email,
                'plan'  => $plan,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
