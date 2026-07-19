<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\NotifyPdpPurchaseInvoicePaidMessage;
use App\Repository\CompanySettingsRepository;
use App\Repository\PurchaseInvoiceRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class NotifyPdpPurchaseInvoicePaidHandler
{
    private const SUPERPDP_BASE_URL = 'https://api.superpdp.tech';

    public function __construct(
        private readonly PurchaseInvoiceRepository $purchaseInvoiceRepository,
        private readonly CompanySettingsRepository $companySettingsRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(NotifyPdpPurchaseInvoicePaidMessage $message): void
    {
        $invoice = $this->purchaseInvoiceRepository->find($message->purchaseInvoiceId);
        if (!$invoice) {
            return;
        }

        $pdpInvoiceId = $invoice->getPdpInvoiceId();
        if (!$pdpInvoiceId) {
            $this->logger->warning('NotifyPdpPurchaseInvoicePaid: no pdpInvoiceId', ['id' => $message->purchaseInvoiceId]);
            return;
        }

        $settings = $this->companySettingsRepository->findOneBy([]);
        if (!$settings || !$settings->getPdpClientId() || !$settings->getPdpApiKey()) {
            return;
        }

        try {
            $token = $this->fetchAccessToken(
                trim($settings->getPdpClientId()),
                trim($settings->getPdpApiKey()),
            );
        } catch (\RuntimeException $e) {
            $this->logger->error('NotifyPdpPurchaseInvoicePaid: OAuth failed', ['error' => $e->getMessage()]);
            return;
        }

        $ch = curl_init(self::SUPERPDP_BASE_URL . '/v1.beta/invoice_events');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(['invoice_id' => $pdpInvoiceId, 'status_code' => 'fr:212']),
        ]);
        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $this->logger->info('NotifyPdpPurchaseInvoicePaid: fr:212 sent', [
                'purchaseInvoiceId' => $message->purchaseInvoiceId,
                'pdpInvoiceId'      => $pdpInvoiceId,
            ]);
        } else {
            $this->logger->error('NotifyPdpPurchaseInvoicePaid: POST /invoice_events failed', [
                'purchaseInvoiceId' => $message->purchaseInvoiceId,
                'pdpInvoiceId'      => $pdpInvoiceId,
                'http'              => $httpCode,
                'response'          => $result,
            ]);
        }
    }

    private function fetchAccessToken(string $clientId, string $clientSecret): string
    {
        $ch = curl_init(self::SUPERPDP_BASE_URL . '/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ]),
        ]);
        $result   = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException(sprintf('OAuth failed (HTTP %d): %s', $httpCode, $result));
        }

        $data = json_decode($result, true);
        if (empty($data['access_token'])) {
            throw new \RuntimeException('OAuth response missing access_token');
        }

        return $data['access_token'];
    }
}
