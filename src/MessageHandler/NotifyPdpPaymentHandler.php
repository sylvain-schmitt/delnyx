<?php

namespace App\MessageHandler;

use App\Message\NotifyPdpPaymentMessage;
use App\Repository\CompanySettingsRepository;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class NotifyPdpPaymentHandler
{
    private const SUPERPDP_BASE_URL = 'https://api.superpdp.tech';

    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly CompanySettingsRepository $companySettingsRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(NotifyPdpPaymentMessage $message): void
    {
        $invoice = $this->invoiceRepository->find($message->invoiceId);
        if (!$invoice || $invoice->getPdpProvider() !== 'superpdp') {
            return;
        }

        $responseData = json_decode($invoice->getPdpResponse() ?? '{}', true);
        $pdpInvoiceId = $responseData['id'] ?? null;
        if (!$pdpInvoiceId) {
            $this->logger->warning('NotifyPdpPayment: no pdp invoice id', ['invoice' => $invoice->getNumero()]);
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
            $this->logger->error('NotifyPdpPayment: OAuth failed', ['error' => $e->getMessage()]);
            return;
        }

        // POST /v1.beta/invoice_events avec fr:212 = encaissée
        $url = self::SUPERPDP_BASE_URL . '/v1.beta/invoice_events';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(['invoice_id' => $pdpInvoiceId, 'status_code' => 'fr:212']),
        ]);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $this->logger->info('NotifyPdpPayment: fr:212 sent to Super PDP', [
                'invoice'      => $invoice->getNumero(),
                'pdpInvoiceId' => $pdpInvoiceId,
            ]);
        } else {
            $this->logger->error('NotifyPdpPayment: POST /invoice_events failed', [
                'invoice'      => $invoice->getNumero(),
                'pdpInvoiceId' => $pdpInvoiceId,
                'http'         => $httpCode,
                'response'     => $result,
            ]);
        }
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
