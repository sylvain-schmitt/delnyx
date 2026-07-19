<?php

namespace App\Command;

use App\Repository\CompanySettingsRepository;
use App\Repository\CreditNoteRepository;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-pdp-status',
    description: 'Synchronise le statut des factures en attente auprès de Super PDP',
)]
class SyncPdpStatusCommand extends Command
{
    private const SUPERPDP_BASE_URL = 'https://api.superpdp.tech';

    // Statuts terminaux : on ne re-poll pas ces factures
    private const TERMINAL_STATUSES = ['ACCEPTED', 'REJECTED', 'ERROR'];

    // Mapping code Super PDP → pdpStatus Delnyx
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
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $settings = $this->companySettingsRepository->findOneBy([]);
        if (!$settings || !$settings->getPdpClientId() || !$settings->getPdpApiKey()) {
            $io->error('Identifiants Super PDP non configurés.');
            return Command::FAILURE;
        }

        $invoices    = $this->invoiceRepository->findBy(['pdpStatus' => ['PENDING', 'DEPOSITED']]);
        $creditNotes = $this->creditNoteRepository->findBy(['pdpStatus' => ['PENDING', 'DEPOSITED']]);

        if (empty($invoices) && empty($creditNotes)) {
            $io->info('Aucune facture ou avoir en attente de mise à jour de statut.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('%d facture(s) et %d avoir(s) à synchroniser.', count($invoices), count($creditNotes)));

        try {
            $token = $this->fetchAccessToken(
                trim($settings->getPdpClientId()),
                trim($settings->getPdpApiKey()),
            );
        } catch (\RuntimeException $e) {
            $io->error('Échec OAuth : ' . $e->getMessage());
            $this->logger->error('SyncPdpStatus: OAuth failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }

        $updated = 0;

        foreach ($invoices as $invoice) {
            $response = $invoice->getPdpResponse();
            if (!$response) {
                continue;
            }

            $data = json_decode($response, true);
            $pdpInvoiceId = $data['id'] ?? null;
            if (!$pdpInvoiceId) {
                $this->logger->warning('SyncPdpStatus: no pdp invoice id', ['invoice' => $invoice->getNumero()]);
                continue;
            }

            try {
                $events = $this->fetchInvoiceEvents((int) $pdpInvoiceId, $token);
            } catch (\RuntimeException $e) {
                $io->warning(sprintf('[%s] Erreur polling : %s', $invoice->getNumero(), $e->getMessage()));
                $this->logger->warning('SyncPdpStatus: polling failed', [
                    'invoice' => $invoice->getNumero(),
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if (empty($events)) {
                $io->writeln(sprintf('[%s] Aucun événement.', $invoice->getNumero()));
                continue;
            }

            $lastEvent = end($events);
            $statusCode = $lastEvent['status_code'] ?? '';
            $newStatus = $this->mapStatus($statusCode);

            $io->writeln(sprintf('[%s] pdp_id=%d  dernier event: %s → %s', $invoice->getNumero(), $pdpInvoiceId, $statusCode, $newStatus ?? '(inchangé)'));

            if ($newStatus && $newStatus !== $invoice->getPdpStatus()) {
                $invoice->setPdpStatus($newStatus);
                $data['events'] = $events;
                $invoice->setPdpResponse(json_encode($data, JSON_UNESCAPED_UNICODE));
                $updated++;
            }
        }

        foreach ($creditNotes as $creditNote) {
            $response = $creditNote->getPdpResponse();
            if (!$response) {
                continue;
            }

            $data = json_decode($response, true);
            $pdpInvoiceId = $data['id'] ?? null;
            if (!$pdpInvoiceId) {
                $this->logger->warning('SyncPdpStatus: no pdp invoice id for credit note', ['creditNote' => $creditNote->getNumber()]);
                continue;
            }

            try {
                $events = $this->fetchInvoiceEvents((int) $pdpInvoiceId, $token);
            } catch (\RuntimeException $e) {
                $io->warning(sprintf('[%s] Erreur polling avoir : %s', $creditNote->getNumber(), $e->getMessage()));
                continue;
            }

            if (empty($events)) {
                $io->writeln(sprintf('[%s] Aucun événement.', $creditNote->getNumber()));
                continue;
            }

            $lastEvent = end($events);
            $statusCode = $lastEvent['status_code'] ?? '';
            $newStatus = $this->mapStatus($statusCode);

            $io->writeln(sprintf('[%s] pdp_id=%d  dernier event: %s → %s', $creditNote->getNumber(), $pdpInvoiceId, $statusCode, $newStatus ?? '(inchangé)'));

            if ($newStatus && $newStatus !== $creditNote->getPdpStatus()) {
                $creditNote->setPdpStatus($newStatus);
                $data['events'] = $events;
                $creditNote->setPdpResponse(json_encode($data, JSON_UNESCAPED_UNICODE));
                $updated++;
            }
        }

        $this->em->flush();
        $io->success(sprintf('%d document(s) mis à jour.', $updated));

        return Command::SUCCESS;
    }

    private function mapStatus(string $statusCode): ?string
    {
        // Correspondance exacte
        if (isset(self::STATUS_MAP[$statusCode])) {
            return self::STATUS_MAP[$statusCode];
        }

        // Préfixes génériques fr:2xx et ppf:*
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
