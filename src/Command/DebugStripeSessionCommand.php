<?php

namespace App\Command;

use App\Service\StripeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug:stripe-session',
    description: 'Debug Stripe Session',
)]
class DebugStripeSessionCommand extends Command
{
    public function __construct(
        private readonly StripeService $stripeService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        // Session ID from previous step
        $sessionId = 'cs_test_b1c2jXQcU81TAVihwNeysVE9j3bufYVNFJYwQLXJsKPwJhoFRCD38pXvgt';

        try {
            $io->info("Retrieving session: $sessionId");
            $session = $this->stripeService->retrieveSession($sessionId);

            if ($session) {
                $io->note("Mode: " . $session->mode);
                $io->note("Subscription ID: " . ($session->subscription ?? 'null'));
                $io->note("Customer: " . ($session->customer instanceof \Stripe\Customer ? $session->customer->id : $session->customer));

                if ($session->mode === 'subscription') {
                    $io->info("Syncing subscription locally...");
                    $this->stripeService->createOrUpdateSubscriptionFromSession($session);
                    $io->success("Subscription synced!");
                }
            } else {
                $io->error("Session not found.");
            }
        } catch (\Throwable $e) {
            $io->error("Exception: " . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
