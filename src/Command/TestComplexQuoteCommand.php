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
    name: 'app:test:complex-quote',
    description: 'Crée un devis complexe (Multi-lignes + Abo + Acompte) pour le test du scénario 6',
)]
class TestComplexQuoteCommand extends Command
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

        // 1. Trouver ou créer un client de test
        $client = $this->clientRepository->findOneBy(['email' => 'test-complex@example.com']);
        if (!$client) {
            $client = new Client();
            $client->setNom('Test');
            $client->setPrenom('Complexe');
            $client->setEmail('test-complex@example.com');
            $client->setAdresse('123 Rue du Test');
            $client->setVille('Paris');
            $client->setCodePostal('75000');
            $this->entityManager->persist($client);
        }

        // 2. Créer le devis
        $quote = new Quote();
        $quote->setClient($client);
        $quote->setStatut(QuoteStatus::SENT);
        $quote->setTauxTVA('10.00'); // TVA 10% globale
        $quote->setAcomptePourcentage('30.00'); // 30% d'acompte

        // Récupérer le companyId réel depuis les settings
        $settings = $this->entityManager->getRepository(\App\Entity\CompanySettings::class)->findOneBy([]);
        $quote->setCompanyId($settings ? $settings->getCompanyId() : '1');

        $quote->setDateCreation(new \DateTime());
        $quote->setDateValidite((new \DateTime())->modify('+30 days'));

        // 3. Ajouter les lignes
        // Ligne 1 : Service standard
        $line1 = new QuoteLine();
        $line1->setDescription('Installation et Configuration Système');
        $line1->setQuantity(1);
        $line1->setUnitPrice('1000.00');
        $line1->setQuote($quote);
        $quote->addLine($line1);

        // Ligne 2 : Abonnement mensuel
        $line2 = new QuoteLine();
        $line2->setDescription('Maintenance et Support Mensuel');
        $line2->setQuantity(1);
        $line2->setUnitPrice('120.00');
        $line2->setSubscriptionMode('monthly');
        $line2->setRecurrenceAmount('120.00');
        $line2->setQuote($quote);
        $quote->addLine($line2);

        // Recalculer les totaux
        $quote->recalculateTotalsFromLines();

        // Générer le numéro
        $quote->setNumero($this->numberGenerator->generate($quote));

        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        // 4. Générer le lien magique
        $signUrl = $this->magicLinkService->generateSignLink($quote);

        $io->success('Devis complexe créé avec succès !');
        $io->info([
            sprintf('Numéro : %s', $quote->getNumero()),
            sprintf('Client : %s', $client->getNomComplet()),
            sprintf('Montant TTC : %s €', number_format((float) $quote->getMontantTTC(), 2, ',', ' ')),
            sprintf('Acompte attendu (30%%) : %s €', number_format((float) $quote->getMontantTTC() * 0.3, 2, ',', ' ')),
            '',
            'LIEN DE SIGNATURE :',
            $signUrl
        ]);

        return Command::SUCCESS;
    }
}
