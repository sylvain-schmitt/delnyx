<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\TransmitInvoiceToPAMessage;
use App\Repository\CompanySettingsRepository;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
class TransmitInvoiceToPAHandler
{
    // Super PDP sandbox / production ont le même domaine, seul pdpMode change côté dashboard
    private const SUPERPDP_BASE_URL = 'https://api.superpdp.tech';

    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private CompanySettingsRepository $companySettingsRepository,
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(TransmitInvoiceToPAMessage $message): void
    {
        $invoice = $this->invoiceRepository->find($message->getInvoiceId());

        if (!$invoice) {
            $this->logger->warning('TransmitInvoiceToPAHandler: invoice not found', [
                'invoiceId' => $message->getInvoiceId(),
            ]);
            return;
        }

        if (!$invoice->isB2BEInvoicing()) {
            $this->logger->info('TransmitInvoiceToPAHandler: invoice is not B2B e-invoicing, skipped', [
                'invoiceId' => $invoice->getId(),
                'mode' => $invoice->getEInvoicingMode(),
            ]);
            return;
        }

        $settings = null;
        if ($invoice->getCompanyId()) {
            $settings = $this->companySettingsRepository->findByCompanyId($invoice->getCompanyId());
        }
        if (!$settings) {
            $settings = $this->companySettingsRepository->findOneBy([]);
        }

        if (!$settings || !$settings->getPdpClientId() || !$settings->getPdpApiKey()) {
            $this->logger->error('TransmitInvoiceToPAHandler: PDP credentials not configured', [
                'invoiceId' => $invoice->getId(),
            ]);
            $invoice->setPdpStatus('ERROR');
            $invoice->setPdpResponse('Configuration PA manquante : client_id ou client_secret non renseignés.');
            $this->entityManager->flush();
            return;
        }

        try {
            $accessToken = $this->fetchAccessToken(
                $settings->getPdpClientId(),
                $settings->getPdpApiKey()
            );
        } catch (\Exception $e) {
            $this->logger->error('TransmitInvoiceToPAHandler: OAuth token error', [
                'invoiceId' => $invoice->getId(),
                'error' => $e->getMessage(),
            ]);
            $invoice->setPdpStatus('ERROR');
            $invoice->setPdpResponse('Erreur OAuth : ' . $e->getMessage());
            $this->entityManager->flush();
            throw $e;
        }

        // Résoudre le company_id Super PDP : en config ou auto-découverte
        $pdpCompanyId = $settings->getPdpCompanyId();
        if (!$pdpCompanyId) {
            try {
                $pdpCompanyId = $this->fetchCompanyId($accessToken);
                $settings->setPdpCompanyId($pdpCompanyId);
                $this->entityManager->flush();
            } catch (\Exception $e) {
                $this->logger->error('TransmitInvoiceToPAHandler: company ID discovery failed', [
                    'invoiceId' => $invoice->getId(),
                    'error' => $e->getMessage(),
                ]);
                $invoice->setPdpStatus('ERROR');
                $invoice->setPdpResponse('Erreur récupération company_id Super PDP : ' . $e->getMessage());
                $this->entityManager->flush();
                return;
            }
        }

        $payload = $this->buildPayload($invoice, $settings, $pdpCompanyId);

        try {
            $response = $this->httpClient->request('POST', self::SUPERPDP_BASE_URL . '/v1.beta/invoices', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getContent(false);

            if ($statusCode >= 200 && $statusCode < 300) {
                $invoice->setPdpStatus('PENDING');
                $invoice->setPdpProvider('superpdp');
                $invoice->setPdpTransmissionDate(new \DateTime());
                $invoice->setPdpResponse($responseBody);

                $this->logger->info('TransmitInvoiceToPAHandler: invoice transmitted to Super PDP', [
                    'invoiceId' => $invoice->getId(),
                    'invoiceNumero' => $invoice->getNumero(),
                    'statusCode' => $statusCode,
                ]);
            } else {
                $invoice->setPdpStatus('ERROR');
                $invoice->setPdpResponse($responseBody);

                $this->logger->error('TransmitInvoiceToPAHandler: Super PDP returned error', [
                    'invoiceId' => $invoice->getId(),
                    'statusCode' => $statusCode,
                    'response' => $responseBody,
                ]);
            }
        } catch (\Exception $e) {
            $invoice->setPdpStatus('ERROR');
            $invoice->setPdpResponse($e->getMessage());
            $this->logger->error('TransmitInvoiceToPAHandler: HTTP exception', [
                'invoiceId' => $invoice->getId(),
                'exception' => $e->getMessage(),
            ]);
            throw $e; // Messenger retry
        }

        $this->entityManager->flush();
    }

    private function fetchAccessToken(string $clientId, string $clientSecret): string
    {
        $response = $this->httpClient->request('POST', self::SUPERPDP_BASE_URL . '/oauth2/token', [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf(
                'OAuth token request failed (HTTP %d): %s',
                $response->getStatusCode(),
                $response->getContent(false)
            ));
        }

        $data = $response->toArray();
        if (empty($data['access_token'])) {
            throw new \RuntimeException('OAuth response missing access_token');
        }

        return $data['access_token'];
    }

    private function fetchCompanyId(string $accessToken): int
    {
        $response = $this->httpClient->request('GET', self::SUPERPDP_BASE_URL . '/v1.beta/companies/me', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf(
                'GET /companies/me failed (HTTP %d): %s',
                $response->getStatusCode(),
                $response->getContent(false)
            ));
        }

        $data = $response->toArray();
        if (empty($data['id'])) {
            throw new \RuntimeException('GET /companies/me response missing id field');
        }

        return (int) $data['id'];
    }

    private function buildPayload(
        \App\Entity\Invoice $invoice,
        \App\Entity\CompanySettings $settings,
        int $pdpCompanyId,
    ): array {
        $client = $invoice->getClient();
        $totalHT = (float) $invoice->getMontantHT();
        $totalTVA = (float) $invoice->getMontantTVA();
        $totalTTC = (float) $invoice->getMontantTTC();

        $lines = [];
        foreach ($invoice->getLines() as $index => $line) {
            $lineHt = (float) ($line->getTotalHt() ?? 0);
            $lines[] = [
                'identifier' => (string) ($index + 1),
                'invoiced_quantity' => (float) ($line->getQuantity() ?? 1),
                'invoiced_quantity_code' => 'C62', // "unité" EN16931
                'net_amount' => number_format($lineHt, 2, '.', ''),
                'price_details' => [
                    'price_amount' => number_format((float) $line->getUnitPrice(), 2, '.', ''),
                ],
                'item_information' => [
                    'name' => $line->getDescription() ?? '',
                ],
            ];
        }

        // EN16931 : franchise de base de TVA → catégorie E, taux 0
        $vatBreakDown = [[
            'tax_amount' => '0.00',
            'tax_base_amount' => number_format($totalHT, 2, '.', ''),
            'category_code' => 'E',
            'rate' => 0,
            'exemption_reason' => 'Article 293 B du CGI',
        ]];

        return [
            'company_id' => $pdpCompanyId,
            'direction' => 'out',
            'external_id' => substr($invoice->getNumero() ?? (string) $invoice->getId(), 0, 36),
            'en_invoice' => [
                'number' => $invoice->getNumero(),
                'issue_date' => $invoice->getDateCreation()?->format('Y-m-d'),
                'type_code' => 380, // Facture standard EN16931
                'currency_code' => 'EUR',
                'process_control' => [
                    // Spec Factur-X France (EN16931 + extension FNFE)
                    'specification_identifier' => 'urn:cen.eu:en16931:2017#compliant#urn:fnfe-membres.fr:2:en16931:2017:amended:2020-05',
                ],
                'seller' => [
                    'name' => $settings->getRaisonSociale(),
                    'postal_address' => [
                        'line_one' => $settings->getAdresse(),
                        'city' => $settings->getVille(),
                        'post_code' => $settings->getCodePostal(),
                        'country_code' => 'FR',
                    ],
                    'identifier' => $settings->getSiren(),
                ],
                'buyer' => [
                    'name' => $client?->getCompanyName() ?? $client?->getNomComplet(),
                    'postal_address' => [
                        'line_one' => $client?->getAdresse(),
                        'city' => $client?->getVille(),
                        'post_code' => $client?->getCodePostal(),
                        'country_code' => 'FR',
                    ],
                    'identifier' => $client?->getSiren(),
                ],
                'lines' => $lines,
                'totals' => [
                    'sum_invoice_lines_amount' => number_format($totalHT, 2, '.', ''),
                    'total_without_vat' => number_format($totalHT, 2, '.', ''),
                    'total_with_vat' => number_format($totalTTC, 2, '.', ''),
                    'amount_due_for_payment' => number_format($totalTTC, 2, '.', ''),
                ],
                'vat_break_down' => $vatBreakDown,
                'payment_due_date' => $invoice->getDateEcheance()?->format('Y-m-d'),
            ],
        ];
    }
}
