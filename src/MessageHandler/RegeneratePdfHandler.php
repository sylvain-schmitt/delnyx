<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RegeneratePdfMessage;
use App\Repository\ClientRepository;
use App\Service\PdfRegenerationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler pour régénérer les PDF en arrière-plan
 */
#[AsMessageHandler]
class RegeneratePdfHandler
{
    public function __construct(
        private readonly PdfRegenerationService $pdfRegenerationService,
        private readonly ClientRepository $clientRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(RegeneratePdfMessage $message): void
    {
        $type = $message->getType();
        $identifier = $message->getIdentifier();

        $this->logger->info('Début de la régénération PDF en arrière-plan', [
            'type' => $type,
            'identifier' => $identifier,
        ]);

        try {
            if ($type === 'company') {
                $stats = $this->pdfRegenerationService->regenerateForCompany($identifier);
            } elseif ($type === 'client') {
                $client = $this->clientRepository->find($identifier);
                if (!$client) {
                    $this->logger->error('Client non trouvé pour régénération PDF', [
                        'client_id' => $identifier,
                    ]);
                    return;
                }
                $stats = $this->pdfRegenerationService->regenerateForClient($client);
            } else {
                $this->logger->error('Type de régénération PDF invalide', [
                    'type' => $type,
                    'identifier' => $identifier,
                ]);
                return;
            }

            $this->logger->info('Régénération PDF terminée avec succès', [
                'type' => $type,
                'identifier' => $identifier,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la régénération PDF en arrière-plan', [
                'type' => $type,
                'identifier' => $identifier,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // Re-throw pour que Messenger gère l'échec
        }
    }
}

