<?php

declare(strict_types=1);

namespace App\Command\Google;

use App\Service\Google\GoogleReviewService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:google-reviews:refresh',
    description: 'Force le rafraîchissement du cache des avis Google Business.',
)]
class RefreshGoogleReviewsCommand extends Command
{
    public function __construct(
        private GoogleReviewService $googleReviewService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Rafraîchissement des avis Google Business');
        $io->info('Appel à l\'API Google Places et mise à jour du cache...');

        try {
            $this->googleReviewService->refreshCache();
            $io->success('Le cache des avis Google a été mis à jour avec succès.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur lors du rafraîchissement : ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
