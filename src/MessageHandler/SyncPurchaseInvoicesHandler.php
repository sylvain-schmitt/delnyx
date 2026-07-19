<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\PurchaseInvoice;
use App\Message\SyncPurchaseInvoicesMessage;
use App\Repository\CompanySettingsRepository;
use App\Repository\PurchaseInvoiceRepository;
use App\Service\PdfGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SyncPurchaseInvoicesHandler
{
    private const SUPERPDP_BASE_URL = 'https://api.superpdp.tech';

    public function __construct(
        private PurchaseInvoiceRepository $purchaseInvoiceRepository,
        private CompanySettingsRepository $companySettingsRepository,
        private EntityManagerInterface $entityManager,
        private PdfGeneratorService $pdfGeneratorService,
        private KernelInterface $kernel,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(SyncPurchaseInvoicesMessage $message): void
    {
        $settings = ($message->getCompanyId()
            ? $this->companySettingsRepository->findByCompanyId($message->getCompanyId())
            : null)
            ?? $this->companySettingsRepository->findOneBy([]);

        if (!$settings || !$settings->getPdpClientId() || !$settings->getPdpApiKey()) {
            $this->logger->warning('SyncPurchaseInvoicesHandler: PDP credentials not configured');
            return;
        }

        try {
            $token = $this->fetchAccessToken(
                trim($settings->getPdpClientId()),
                trim($settings->getPdpApiKey())
            );
        } catch (\Exception $e) {
            $this->logger->error('SyncPurchaseInvoicesHandler: OAuth failed', ['error' => $e->getMessage()]);
            return;
        }

        $ch = curl_init(self::SUPERPDP_BASE_URL . '/v1.beta/invoices?direction=in');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
        ]);
        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->logger->error('SyncPurchaseInvoicesHandler: GET /invoices?direction=in failed', [
                'http'     => $httpCode,
                'response' => $responseBody,
            ]);
            return;
        }

        $data  = json_decode($responseBody, true);
        $items = $data['items'] ?? $data['data'] ?? (is_array($data) && isset($data[0]) ? $data : []);

        $companyId = $message->getCompanyId() ?? $settings->getCompanyId() ?? '';
        $synced    = 0;

        foreach ($items as $item) {
            $pdpInvoiceId = (int) ($item['id'] ?? 0);
            if (!$pdpInvoiceId) {
                continue;
            }

            $existing = $this->purchaseInvoiceRepository->findByPdpInvoiceId($companyId, $pdpInvoiceId);
            $isNew = !$existing;

            if (!$existing) {
                $existing = new PurchaseInvoice();
                $existing->setCompanyId($companyId);
                $existing->setPdpInvoiceId($pdpInvoiceId);
                $this->entityManager->persist($existing);
            }

            // Fetch full invoice details
            $detail = $this->fetchInvoiceDetail($pdpInvoiceId, $token);
            if ($detail) {
                $this->populateFromDetail($existing, $detail);
            } else {
                // Fallback to list data
                $existing->setExternalId((string) ($item['external_id'] ?? ''));
                $existing->setPdpStatus($item['status_code'] ?? $item['status'] ?? null);
                $existing->setPdpRawResponse(json_encode($item));
            }

            // Generate PDF for new invoices (or if PDF is missing)
            if ($isNew || !$existing->getLocalPdfPath()) {
                $this->generatePdf($existing);
            }

            $synced++;
        }

        $this->entityManager->flush();

        $this->logger->info('SyncPurchaseInvoicesHandler: sync complete', [
            'companyId' => $companyId,
            'synced'    => $synced,
        ]);
    }

    private function fetchInvoiceDetail(int $pdpInvoiceId, string $token): ?array
    {
        $ch = curl_init(self::SUPERPDP_BASE_URL . '/v1.beta/invoices/' . $pdpInvoiceId);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            $this->logger->warning('SyncPurchaseInvoicesHandler: GET /invoices/{id} failed', [
                'id' => $pdpInvoiceId, 'http' => $code,
            ]);
            return null;
        }

        return json_decode($body, true);
    }

    private function populateFromDetail(PurchaseInvoice $invoice, array $detail): void
    {
        $en = $detail['en_invoice'] ?? [];

        $invoice->setPdpRawResponse(json_encode($detail, JSON_UNESCAPED_UNICODE));

        // Last event status
        $events = $detail['events'] ?? [];
        if (!empty($events)) {
            $last = end($events);
            $invoice->setPdpStatus($last['status_code'] ?? null);
        }

        $invoice->setInvoiceNumber($en['number'] ?? null);
        $invoice->setExternalId((string) ($detail['id'] ?? ''));

        if (!empty($en['issue_date'])) {
            try { $invoice->setIssueDate(new \DateTime($en['issue_date'])); } catch (\Exception) {}
        }

        $seller = $en['seller'] ?? [];
        $invoice->setSellerName($seller['name'] ?? null);
        $invoice->setSellerSiren(
            $seller['legal_registration_identifier']['value']
            ?? $seller['vat_identifier']
            ?? null
        );

        $buyer = $en['buyer'] ?? [];
        $invoice->setBuyerName($buyer['name'] ?? null);

        $totals = $en['totals'] ?? [];
        if (isset($totals['total_without_vat'])) {
            $invoice->setTotalHT((string) $totals['total_without_vat']);
        }
        if (isset($totals['total_with_vat'])) {
            $invoice->setTotalTTC((string) $totals['total_with_vat']);
        }

        $invoice->setLines($en['lines'] ?? []);
        $invoice->setVatBreakdown($en['vat_break_down'] ?? []);

        if ($invoice->getPdpStatus() === 'fr:212' && $invoice->getLocalStatus() !== 'paid') {
            $invoice->setLocalStatus('paid');
        }
    }

    private function generatePdf(PurchaseInvoice $invoice): void
    {
        try {
            $dir = $this->kernel->getProjectDir() . '/var/purchase_invoices';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $detail    = json_decode($invoice->getPdpRawResponse() ?? '{}', true);
            $en        = $detail['en_invoice'] ?? [];
            $filename  = 'achat-' . $invoice->getPdpInvoiceId() . '.pdf';
            $filePath  = $dir . '/' . $filename;

            $result = $this->pdfGeneratorService->generateAndSavePdf(
                'pdf/achat.html.twig',
                [
                    'invoice'  => $invoice,
                    'en'       => $en,
                    'filename' => 'achat-' . ($invoice->getInvoiceNumber() ?? $invoice->getPdpInvoiceId()),
                ],
                'purchase_invoices'
            );

            $invoice->setLocalPdfPath($result['filename']);

            $this->logger->info('SyncPurchaseInvoicesHandler: PDF generated', [
                'pdpId' => $invoice->getPdpInvoiceId(),
                'file'  => $result['filename'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('SyncPurchaseInvoicesHandler: PDF generation failed', [
                'pdpId' => $invoice->getPdpInvoiceId(),
                'error' => $e->getMessage(),
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
            throw new \RuntimeException(sprintf('OAuth token request failed (HTTP %d): %s', $httpCode, $result));
        }

        $data = json_decode($result, true);
        if (empty($data['access_token'])) {
            throw new \RuntimeException('OAuth response missing access_token: ' . $result);
        }

        return $data['access_token'];
    }
}
