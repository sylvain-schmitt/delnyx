<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\QuoteRepository;
use App\Repository\AmendmentRepository;
use App\Repository\InvoiceRepository;
use App\Service\MagicLinkService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:magic-link:generate',
    description: 'Génère un magic link pour tester les pages publiques',
)]
class GenerateMagicLinkCommand extends Command
{
    public function __construct(
        private MagicLinkService $magicLinkService,
        private QuoteRepository $quoteRepository,
        private AmendmentRepository $amendmentRepository,
        private InvoiceRepository $invoiceRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('type', InputArgument::REQUIRED, 'Type de document (quote, amendment, invoice)')
            ->addArgument('id', InputArgument::REQUIRED, 'ID du document')
            ->addArgument('action', InputArgument::REQUIRED, 'Action (view, sign, refuse, pay)')
            ->addArgument('days', InputArgument::OPTIONAL, 'Nombre de jours de validité (défaut: 30)', 30);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $type = $input->getArgument('type');
        $id = (int) $input->getArgument('id');
        $action = $input->getArgument('action');
        $days = (int) $input->getArgument('days');

        // Récupérer le document
        $document = match ($type) {
            'quote' => $this->quoteRepository->find($id),
            'amendment' => $this->amendmentRepository->find($id),
            'invoice' => $this->invoiceRepository->find($id),
            default => null,
        };

        if (!$document) {
            $io->error("Document $type avec l'ID $id introuvable.");
            return Command::FAILURE;
        }

        // Générer le magic link
        try {
            $magicLink = $this->magicLinkService->generatePublicLink($document, $action, $days);

            $io->success('Magic link généré avec succès !');
            $io->section('Informations');
            $io->table(
                ['Propriété', 'Valeur'],
                [
                    ['Type', $type],
                    ['ID', $id],
                    ['Action', $action],
                    ['Validité', "$days jours"],
                    ['Document', $document->getNumero() ?? "Document #$id"],
                ]
            );

            $io->section('Magic Link');
            $io->text($magicLink);
            $io->newLine();
            $io->note('Copiez ce lien et ouvrez-le dans votre navigateur pour tester.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur lors de la génération du magic link : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
