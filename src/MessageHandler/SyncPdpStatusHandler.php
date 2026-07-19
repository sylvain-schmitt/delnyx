<?php

namespace App\MessageHandler;

use App\Message\SyncPdpStatusMessage;
use App\Repository\CompanySettingsRepository;
use App\Repository\CreditNoteRepository;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SyncPdpStatusHandler
{
    private const SUPERPDP_BASE_URL = 'https://api.superpdp.tech';

    private const STATUS_MAP = [
        'api:uploaded'  => 'PENDING',
        'api:validated' => 'PENDING',
        'api:sent'      => 'DEPOSITED',
        'api:accepted'  => 'ACCEPTED',
        'api:rejected'  => 'REJECTED',
        'api:invalid'   => 'ERROR',
        'fr:200'        => 'DEPOSITED',
        'fr:211'        => 'DEPOSITED',
        'fr:212'        => 'ACCEPTED',
        'fr:213'        => 'REJECTED',
        'ppf:received'  => 'DEPOSITED',
        'ppf:accepted'  => 'ACCEPTED',
        'ppf:rejected'  => 'REJECTED',
    ];

    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly CreditNoteRepository $creditNoteRepository,
        private readonly CompanySettingsRepository $companySettingsRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncPdpStatusMessage $message): void
    {
        $settings = $this->companySettingsRepository->findOneBy([]);
        if (!$settings || !$settings->getPdpClientId() || !$settings->getPdpApiKey()) {
            return;
        }

        $invoices = $this->invoiceRepository->findBy(['pdpStatus' => ['PENDING', 'DEPOSITED']]);
        $creditNotes = $this->creditNoteRepository->findBy(['pdpStatus' => ['PENDING', 'DEPOSITED']]);

        if (empty($invoices) && empty($creditNotes)) {
            return;
        }

        try {
            $token = $this->fetchAccessToken(
                trim($settings->getPdpClientId()),
                trim($settings->getPdpApiKey()),
            );
        } catch (\RuntimeException $e) {
            $this->logger->error('SyncPdpStatus: OAuth failed', ['error' => $e->getMessage()]);
            return;
        }

        foreach ($invoices as $invoice) {
            $responseData = json_decode($invoice->getPdpResponse() ?? '{}', true);
            $pdpInvoiceId = $responseData['id'] ?? null;
            if (!$pdpInvoiceId) {
                continue;
            }

            try {
                $events = $this->fetchInvoiceEvents((int) $pdpInvoiceId, $token);
            } catch (\RuntimeException $e) {
                $this->logger->warning('SyncPdpStatus: polling failed', [
                    'invoice' => $invoice->getNumero(),
                    'error'   => $e->getMessage(),
                ]);
                continue;
            }

            if (empty($events)) {
                continue;
            }

            $lastEvent = end($events);
            $statusCode = $lastEvent['status_code'] ?? '';
            $newStatus = $this->mapStatus($statusCode);

            if ($newStatus && $newStatus !== $invoice->getPdpStatus()) {
                $this->logger->info('SyncPdpStatus: invoice status updated', [
                    'invoice' => $invoice->getNumero(),
                    'old'     => $invoice->getPdpStatus(),
                    'new'     => $newStatus,
                    'event'   => $statusCode,
                ]);

                $invoice->setPdpStatus($newStatus);
                $responseData['events'] = $events;
                $invoice->setPdpResponse(json_encode($responseData, JSON_UNESCAPED_UNICODE));
            }
        }

        foreach ($creditNotes as $creditNote) {
            $responseData = json_decode($creditNote->getPdpResponse() ?? '{}', true);
            $pdpInvoiceId = $responseData['id'] ?? null;
            if (!$pdpInvoiceId) {
                continue;
            }

            try {
                $events = $this->fetchInvoiceEvents((int) $pdpInvoiceId, $token);
            } catch (\RuntimeException $e) {
                $this->logger->warning('SyncPdpStatus: credit note polling failed', [
                    'creditNote' => $creditNote->getNumber(),
                    'error'      => $e->getMessage(),
                ]);
                continue;
            }

            if (empty($events)) {
                continue;
            }

            $lastEvent = end($events);
            $statusCode = $lastEvent['status_code'] ?? '';
            $newStatus = $this->mapStatus($statusCode);

            if ($newStatus && $newStatus !== $creditNote->getPdpStatus()) {
                $this->logger->info('SyncPdpStatus: credit note status updated', [
                    'creditNote' => $creditNote->getNumber(),
                    'old'        => $creditNote->getPdpStatus(),
                    'new'        => $newStatus,
                    'event'      => $statusCode,
                ]);

                $creditNote->setPdpStatus($newStatus);
                $responseData['events'] = $events;
                $creditNote->setPdpResponse(json_encode($responseData, JSON_UNESCAPED_UNICODE));
            }
        }

        $this->em->flush();
    }

    private function mapStatus(string $statusCode): ?string
    {
        if (isset(self::STATUS_MAP[$statusCode])) {
            return self::STATUS_MAP[$statusCode];
        }

        if (str_starts_with($statusCode, 'fr:')) {
            $code = (int) substr($statusCode, 3);
            if ($code >= 300) return 'REJECTED';
            if ($code >= 210) return 'ACCEPTED';
            return 'DEPOSITED';
        }

        return null;
    }

    private function fetchInvoiceEvents(int $pdpInvoiceId, string $token): array
    {
        $url = self::SUPERPDP_BASE_URL . '/v1.beta/invoice_events?' . http_build_query(['invoice_id' => $pdpInvoiceId]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
        ]);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException(sprintf('GET /invoice_events failed (HTTP %d): %s', $httpCode, $result));
        }

        $data = json_decode($result, true);

        return $data['data'] ?? $data['items'] ?? [];
    }

    private function fetchAccessToken(string $clientId, string $clientSecret): string
    {
        $ch = curl_init(self::SUPERPDP_BASE_URL . '/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ]),
        ]);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException(sprintf('OAuth token request failed (HTTP %d): %s', $httpCode, $result));
        }

        $data = json_decode($result, true);
        if (empty($data['access_token'])) {
            throw new \RuntimeException('OAuth response missing access_token: ' . $result);
        }

        return $data['access_token'];
    }
}
