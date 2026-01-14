<?php

namespace App\Command;

use App\Service\PdfRegenerationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:pdf:regenerate',
    description: 'Régénère tous les PDF pour une entreprise',
)]
class RegeneratePdfCommand extends Command
{
    public function __construct(
        private readonly PdfRegenerationService $pdfRegenerationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('companyId', InputArgument::REQUIRED, 'L\'ID de l\'entreprise (hash)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $companyId = $input->getArgument('companyId');

        $io->title('Régénération des PDF');
        $io->info("Company ID: $companyId");

        try {
            $stats = $this->pdfRegenerationService->regenerateForCompany($companyId);

            $io->success('Régénération terminée !');
            $io->table(
                ['Type', 'Nombre régénéré'],
                [
                    ['Devis', $stats['quotes']],
                    ['Factures', $stats['invoices']],
                    ['Avenants', $stats['amendments']],
                    ['Avoirs', $stats['credit_notes']],
                    ['Erreurs', $stats['errors']],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
