<?php

namespace App\Service;

use Symfony\Component\Process\Process;
use Psr\Log\LoggerInterface;

class IconImportService
{
    public function __construct(
        private string $projectDir,
        private ?LoggerInterface $logger = null
    ) {}

    /**
     * Importe automatiquement une icône
     */
    public function importIcon(string $iconName): bool
    {
        try {
            // Vérifier si l'icône contient un préfixe (package:icon)
            if (!str_contains($iconName, ':')) {
                return false;
            }

            // Lancer la commande d'importation
            $process = new Process([
                'php', 'bin/console', 'ux:icons:import', $iconName
            ]);
            $process->setWorkingDirectory($this->projectDir);
            $process->run();

            if ($process->isSuccessful()) {
                $this->logger?->info("Icône importée avec succès: {$iconName}");
                return true;
            } else {
                $this->logger?->error("Erreur lors de l'importation de l'icône {$iconName}: " . $process->getErrorOutput());
                return false;
            }
        } catch (\Exception $e) {
            $this->logger?->error("Exception lors de l'importation de l'icône {$iconName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Importe plusieurs icônes
     */
    public function importIcons(array $iconNames): array
    {
        $results = [];
        foreach ($iconNames as $iconName) {
            $results[$iconName] = $this->importIcon($iconName);
        }
        return $results;
    }
}
