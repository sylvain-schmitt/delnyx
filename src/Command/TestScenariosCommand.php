<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Client;
use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Entity\QuoteStatus;
use App\Repository\ClientRepository;
use App\Service\MagicLinkService;
use App\Service\QuoteNumberGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:scenarios',
    description: 'Crée 3 scénarios de test (Standard, Sans TVA, Paiement Manuel)',
)]
class TestScenariosCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClientRepository $clientRepository,
        private readonly MagicLinkService $magicLinkService,
        private readonly QuoteNumberGenerator $numberGenerator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Scenario 1: Standard with TVA
        $this->createScenario($io, 'standard@test.com', 'Standard', '10.00');

        // Scenario 2: No TVA
        $this->createScenario($io, 'notva@test.com', 'NoTVA', '0.00');

        // Scenario 3: Manual Payment (Marked as paid later)
        $this->createScenario($io, 'manual@test.com', 'Manual', '0.00', true);

        return Command::SUCCESS;
    }

    private function createScenario(SymfonyStyle $io, string $email, string $name, string $tvaRate, bool $isManual = false): void
    {
        $client = $this->clientRepository->findOneBy(['email' => $email]);
        if (!$client) {
            $client = new Client();
            $client->setNom($name);
            $client->setPrenom('Test');
            $client->setEmail($email);
            $client->setAdresse('Rue du Scenario');
            $client->setVille('Vichy');
            $client->setCodePostal('03200');
            $this->entityManager->persist($client);
        }

        $quote = new Quote();
        $quote->setClient($client);
        $quote->setStatut(QuoteStatus::SENT);
        $quote->setTauxTVA($tvaRate);

        $settings = $this->entityManager->getRepository(\App\Entity\CompanySettings::class)->findOneBy([]);
        $quote->setCompanyId($settings ? $settings->getCompanyId() : '1');
        $quote->setDateCreation(new \DateTime());
        $quote->setDateValidite((new \DateTime())->modify('+30 days'));

        // Lines
        $line1 = new QuoteLine();
        $line1->setDescription('Service ' . $name);
        $line1->setQuantity(1);
        $line1->setUnitPrice('500.00');
        $line1->setQuote($quote);
        $quote->addLine($line1);

        $line2 = new QuoteLine();
        $line2->setDescription('Abo ' . $name);
        $line2->setQuantity(1);
        $line2->setUnitPrice('50.00');
        $line2->setSubscriptionMode('monthly');
        $line2->setRecurrenceAmount('50.00');
        $line2->setQuote($quote);
        $quote->addLine($line2);

        $quote->recalculateTotalsFromLines();
        $quote->setNumero($this->numberGenerator->generate($quote));

        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        $signUrl = $this->magicLinkService->generateSignLink($quote);

        $io->section("Scénario : $name");
        $io->text("Client : $email");
        $io->text("TVA : $tvaRate%");
        $io->text("Lien : $signUrl");
        if ($isManual) {
            $io->note("Ce devis devra être signé, puis réglé par chèque/virement");
        }
    }
}
