<?php

namespace App\Command;

use App\Entity\Client;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix:stripe-customer',
    description: 'Manually sync Stripe Customer ID from session',
)]
class FixStripeCustomerCommand extends Command
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sessionId = 'cs_test_a1VAQHV0Gt1out7CDEQ6wmq9nfFjPIcc9SFXRCoaLDg9PxH6MuR5JQgKrE';
        $clientId = 678;

        try {
            $io->info("Retrieving session: $sessionId");
            $session = $this->stripeService->retrieveSession($sessionId);

            if ($session) {
                $io->note("Session retrieved. Mode: " . $session->mode);
                $io->note("Customer (raw): " . var_export($session->customer, true));

                // If expanded, customer is an object
                $customerId = null;
                if (is_object($session->customer)) {
                    $customerId = $session->customer->id;
                } elseif (is_string($session->customer)) {
                    $customerId = $session->customer;
                }

                if ($customerId) {
                    $io->success("Found Customer ID: " . $customerId);

                    $client = $this->entityManager->getRepository(Client::class)->find($clientId);
                    if ($client) {
                        $client->setStripeCustomerId($customerId);
                        $this->entityManager->flush();
                        $io->success("Client updated!");
                    } else {
                        $io->error("Client $clientId not found.");
                    }
                } else {
                    $io->error("Customer ID not found in session.");
                }
            } else {
                $io->error("Session not found (null returned).");
            }
        } catch (\Throwable $e) {
            $io->error("Exception: " . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
