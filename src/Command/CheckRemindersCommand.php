<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande pour vérifier et envoyer les relances automatiques
 * À exécuter quotidiennement via CRON
 */
#[AsCommand(
    name: 'app:check-reminders',
    description: 'Vérifie les factures en retard et dispatche les relances à envoyer',
)]
class CheckRemindersCommand extends Command
{
    public function __construct(
        private ReminderService $reminderService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule l\'exécution sans envoyer')
            ->addOption('company-id', null, InputOption::VALUE_REQUIRED, 'Limiter à une entreprise spécifique');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $companyId = $input->getOption('company-id');

        $io->title('Vérification des relances automatiques');

        if ($dryRun) {
            $io->warning('Mode simulation activé - aucune relance ne sera envoyée');
        }

        try {
            $stats = $this->reminderService->processReminders($companyId);

            $io->success(sprintf(
                'Terminé : %d factures vérifiées, %d relances dispatchées, %d ignorées',
                $stats['checked'],
                $stats['dispatched'],
                $stats['skipped']
            ));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur lors du traitement : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
