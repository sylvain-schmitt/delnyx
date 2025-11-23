<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Client;
use App\Entity\ClientStatus;
use App\Entity\Quote;
use App\Entity\QuoteStatus;
use App\Entity\QuoteLine;
use App\Entity\Amendment;
use App\Entity\AmendmentStatus;
use App\Entity\AmendmentLine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:create-test-quotes-amendments',
    description: 'Crée des devis et avenants de test pour vérifier les calculs et l\'affichage'
)]
class CreateTestQuotesAndAmendmentsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'clear',
            'c',
            InputOption::VALUE_NONE,
            'Supprime les données de test existantes avant de créer les nouvelles'
        );
        $this->addOption(
            'user-email',
            'u',
            InputOption::VALUE_REQUIRED,
            'Email de l\'utilisateur connecté pour générer le companyId (par défaut: sylvain.schmitt70@gmail.com)',
            'sylvain.schmitt70@gmail.com'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('clear')) {
            $io->warning('Suppression des données de test existantes...');
            $this->clearTestData($io);
        }

        $io->title('Création de devis et avenants de test');

        // Utiliser le client existant
        $clientEmail = 'devil.9068@gmail.com';
        $client = $this->entityManager->getRepository(Client::class)
            ->findOneBy(['email' => $clientEmail]);

        if (!$client) {
            $io->error('Client non trouvé avec l\'email : ' . $clientEmail);
            $io->note('Veuillez créer le client manuellement ou vérifier l\'email.');
            return Command::FAILURE;
        }

        $io->success('Client existant utilisé : ' . $client->getNom() . ' ' . $client->getPrenom() . ' (ID: ' . $client->getId() . ')');

        // Company ID : utiliser l'email de l'utilisateur connecté pour générer le companyId
        // IMPORTANT : Le companyId doit correspondre à celui de l'utilisateur connecté pour que les avenants soient accessibles
        // Récupérer l'email de l'utilisateur connecté (par défaut: sylvain.schmitt70@gmail.com)
        $userEmail = $input->getOption('user-email') ?: 'sylvain.schmitt70@gmail.com';
        $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
        $companyId = Uuid::v5($namespace, $userEmail)->toString();
        $io->note('Company ID utilisé (basé sur l\'email utilisateur : ' . $userEmail . ') : ' . $companyId);

        // 1. Devis sans TVA (micro-entreprise) - 1 ligne
        $quote1 = $this->createQuote(
            $client,
            $companyId,
            'DEV-TEST-001',
            QuoteStatus::SIGNED,
            '0.00',
            false,
            0,
            'Devis sans TVA - 1 ligne'
        );
        $this->addQuoteLine($quote1, 'Service de base', 1, '1000.00', null);
        $this->entityManager->persist($quote1);
        $this->entityManager->flush();
        $quote1->recalculateTotalsFromLines();
        $this->entityManager->flush();
        $io->success('Devis 1 créé : Sans TVA, 1 ligne, Total = ' . $quote1->getMontantTTCFormate());

        // Avenant pour le devis 1 : modification de ligne
        $amendment1 = $this->createAmendment(
            $quote1,
            $companyId,
            AmendmentStatus::DRAFT,
            '0.00',
            'Avenant 1 - Modification ligne (réduction)'
        );
        $quoteLine1 = $quote1->getLines()->first();
        $this->addAmendmentLine($amendment1, $quoteLine1, 'Service de base modifié', 1, '-200.00', null);
        $this->entityManager->persist($amendment1);
        $this->entityManager->flush();
        $amendment1->recalculateTotalsFromLines();
        $this->entityManager->flush();
        $io->success('Avenant 1 créé : Modification ligne (-200€)');

        // 2. Devis sans TVA - 3 lignes
        $quote2 = $this->createQuote(
            $client,
            $companyId,
            'DEV-TEST-002',
            QuoteStatus::SIGNED,
            '0.00',
            false,
            30,
            'Devis sans TVA - 3 lignes avec acompte 30%'
        );
        $this->addQuoteLine($quote2, 'Service Premium', 1, '2000.00', null);
        $this->addQuoteLine($quote2, 'Support mensuel', 3, '150.00', null);
        $this->addQuoteLine($quote2, 'Formation', 1, '500.00', null);
        $this->entityManager->persist($quote2);
        $this->entityManager->flush();
        $quote2->recalculateTotalsFromLines();
        $this->entityManager->flush();
        $io->success('Devis 2 créé : Sans TVA, 3 lignes, Total = ' . $quote2->getMontantTTCFormate());

        // Avenant pour le devis 2 : modification + nouvelle ligne
        // Créer d'abord en DRAFT, puis changer en SIGNED après avoir ajouté les lignes
        $amendment2 = $this->createAmendment(
            $quote2,
            $companyId,
            AmendmentStatus::DRAFT,
            '0.00',
            'Avenant 2 - Modification + nouvelle ligne'
        );
        $quoteLine2First = $quote2->getLines()->first();
        $this->addAmendmentLine($amendment2, $quoteLine2First, 'Service Premium modifié', 1, '-500.00', null);
        $this->addAmendmentLine($amendment2, null, 'Service supplémentaire', 1, '800.00', null);
        $this->entityManager->persist($amendment2);
        $this->entityManager->flush();
        $amendment2->recalculateTotalsFromLines();
        $this->entityManager->flush();
        // Maintenant on peut changer le statut en SIGNED
        $amendment2->setStatut(AmendmentStatus::SIGNED);
        $this->entityManager->flush();
        $io->success('Avenant 2 créé : Modification (-500€) + Nouvelle ligne (+800€)');

        // 3. Devis avec TVA globale 20% - 2 lignes
        $quote3 = $this->createQuote(
            $client,
            $companyId,
            'DEV-TEST-003',
            QuoteStatus::SIGNED,
            '20.00',
            false,
            0,
            'Devis avec TVA globale 20% - 2 lignes'
        );
        $this->addQuoteLine($quote3, 'Développement web', 1, '3000.00', null);
        $this->addQuoteLine($quote3, 'Hébergement annuel', 1, '600.00', null);
        $this->entityManager->persist($quote3);
        $this->entityManager->flush();
        $quote3->recalculateTotalsFromLines();
        $this->entityManager->flush();
        $io->success('Devis 3 créé : TVA 20% globale, 2 lignes, Total = ' . $quote3->getMontantTTCFormate());

        // Avenant pour le devis 3 : augmentation
        $amendment3 = $this->createAmendment(
            $quote3,
            $companyId,
            AmendmentStatus::DRAFT,
            '20.00',
            'Avenant 3 - Augmentation de prix'
        );
        $quoteLine3First = $quote3->getLines()->first();
        $this->addAmendmentLine($amendment3, $quoteLine3First, 'Développement web étendu', 1, '1000.00', null);
        $this->entityManager->persist($amendment3);
        $this->entityManager->flush();
        $amendment3->recalculateTotalsFromLines();
        $this->entityManager->flush();
        $io->success('Avenant 3 créé : Augmentation (+1000€ HT)');

        // 4. Devis avec TVA par ligne - 4 lignes avec taux différents
        $quote4 = $this->createQuote(
            $client,
            $companyId,
            'DEV-TEST-004',
            QuoteStatus::SIGNED,
            '20.00',
            true,
            25,
            'Devis avec TVA par ligne - 4 lignes avec acompte 25%'
        );
        $this->addQuoteLine($quote4, 'Service standard (20%)', 2, '1000.00', '20.00');
        $this->addQuoteLine($quote4, 'Service réduit (10%)', 1, '500.00', '10.00');
        $this->addQuoteLine($quote4, 'Service exonéré (0%)', 1, '300.00', '0.00');
        $this->addQuoteLine($quote4, 'Service réduit (5.5%)', 1, '200.00', '5.50');
        $this->entityManager->persist($quote4);
        $this->entityManager->flush();
        $quote4->recalculateTotalsFromLines();
        $this->entityManager->flush();
        $io->success('Devis 4 créé : TVA par ligne, 4 lignes, Total = ' . $quote4->getMontantTTCFormate());

        // Avenant pour le devis 4 : modifications multiples
        // Créer d'abord en DRAFT, puis changer en SENT après avoir ajouté les lignes
        $amendment4 = $this->createAmendment(
            $quote4,
            $companyId,
            AmendmentStatus::DRAFT,
            '20.00',
            'Avenant 4 - Modifications multiples'
        );
        $quoteLines4 = $quote4->getLines()->toArray();
        $this->addAmendmentLine($amendment4, $quoteLines4[0], 'Service standard modifié', 1, '-100.00', '20.00');
        $this->addAmendmentLine($amendment4, $quoteLines4[2], 'Service exonéré modifié', 1, '50.00', '0.00');
        $this->addAmendmentLine($amendment4, null, 'Nouveau service', 1, '400.00', '20.00');
        $this->entityManager->persist($amendment4);
        $this->entityManager->flush();
        $amendment4->recalculateTotalsFromLines();
        $this->entityManager->flush();
        // Maintenant on peut changer le statut en SENT
        $amendment4->setStatut(AmendmentStatus::SENT);
        $this->entityManager->flush();
        $io->success('Avenant 4 créé : 2 modifications + 1 nouvelle ligne');

        // 5. Devis complexe sans TVA - 5 lignes avec acompte
        $quote5 = $this->createQuote(
            $client,
            $companyId,
            'DEV-TEST-005',
            QuoteStatus::SIGNED,
            '0.00',
            false,
            40,
            'Devis complexe sans TVA - 5 lignes avec acompte 40%'
        );
        $this->addQuoteLine($quote5, 'Conception', 1, '2500.00', null);
        $this->addQuoteLine($quote5, 'Développement', 1, '5000.00', null);
        $this->addQuoteLine($quote5, 'Tests', 1, '1000.00', null);
        $this->addQuoteLine($quote5, 'Déploiement', 1, '800.00', null);
        $this->addQuoteLine($quote5, 'Documentation', 1, '700.00', null);
        $this->entityManager->persist($quote5);
        $this->entityManager->flush();
        $quote5->recalculateTotalsFromLines();
        $this->entityManager->flush();
        $io->success('Devis 5 créé : Sans TVA, 5 lignes, Total = ' . $quote5->getMontantTTCFormate());

        // Avenant pour le devis 5 : réduction importante
        $amendment5 = $this->createAmendment(
            $quote5,
            $companyId,
            AmendmentStatus::DRAFT,
            '0.00',
            'Avenant 5 - Réduction importante'
        );
        $quoteLine5Dev = $quote5->getLines()->filter(fn($l) => $l->getDescription() === 'Développement')->first();
        if ($quoteLine5Dev) {
            $this->addAmendmentLine($amendment5, $quoteLine5Dev, 'Développement réduit', 1, '-1500.00', null);
        }
        $this->entityManager->persist($amendment5);
        $this->entityManager->flush();
        $amendment5->recalculateTotalsFromLines();
        $this->entityManager->flush();
        $io->success('Avenant 5 créé : Réduction importante (-1500€)');

        $io->newLine();
        $io->success([
            '✅ Tous les devis et avenants de test ont été créés avec succès !',
            '',
            'Résumé :',
            '- 5 devis créés (avec et sans TVA, avec et sans acompte)',
            '- 5 avenants créés (modifications, ajouts, différents statuts)',
            '- Client de test : ' . $client->getNom() . ' ' . $client->getPrenom(),
            '',
            'Vous pouvez maintenant vérifier les calculs et l\'affichage dans l\'interface admin.'
        ]);

        return Command::SUCCESS;
    }

    private function createTestClient(): Client
    {
        $client = new Client();
        $client->setNom('Dupont');
        $client->setPrenom('Jean');
        $client->setEmail('jean.dupont@test.fr');
        $client->setTelephone('0123456789');
        $client->setAdresse('123 Rue de Test');
        $client->setCodePostal('75001');
        $client->setVille('Paris');
        $client->setPays('France');
        $client->setStatut(ClientStatus::ACTIF);
        $client->setDateCreation(new \DateTime());
        $client->setDateModification(new \DateTime());

        return $client;
    }

    private function createQuote(
        Client $client,
        string $companyId,
        string $numero,
        QuoteStatus $statut,
        string $tauxTVA,
        bool $usePerLineTva,
        int $acomptePourcentage,
        string $notes
    ): Quote {
        $quote = new Quote();
        $quote->setClient($client);
        $quote->setCompanyId($companyId);
        $quote->setNumero($numero);
        $quote->setStatut($statut);
        $quote->setTauxTVA($tauxTVA);
        $quote->setUsePerLineTva($usePerLineTva);
        $quote->setAcomptePourcentage((string) $acomptePourcentage);
        $quote->setNotes($notes);
        $quote->setDateCreation(new \DateTime());
        $quote->setDateModification(new \DateTime());
        
        $dateValidite = new \DateTime();
        $dateValidite->modify('+30 days');
        $quote->setDateValidite($dateValidite);

        return $quote;
    }

    private function addQuoteLine(
        Quote $quote,
        string $description,
        int $quantity,
        string $unitPrice,
        ?string $tvaRate
    ): QuoteLine {
        $line = new QuoteLine();
        $line->setQuote($quote);
        $line->setDescription($description);
        $line->setQuantity($quantity);
        $line->setUnitPrice($unitPrice);
        if ($tvaRate !== null) {
            $line->setTvaRate($tvaRate);
        }
        $line->setIsCustom(true);
        $line->recalculateTotalHt();
        $quote->addLine($line);

        return $line;
    }

    private function createAmendment(
        Quote $quote,
        string $companyId,
        AmendmentStatus $statut,
        string $tauxTVA,
        string $justification
    ): Amendment {
        $amendment = new Amendment();
        $amendment->setQuote($quote);
        $amendment->setCompanyId($companyId);
        $amendment->setStatut($statut);
        $amendment->setTauxTVA($tauxTVA);
        $amendment->setMotif($justification); // motif est obligatoire
        $amendment->setJustification($justification);
        $amendment->setModifications($justification); // modifications est aussi obligatoire
        $amendment->setDateCreation(new \DateTime());
        $amendment->setDateModification(new \DateTime());

        return $amendment;
    }

    private function addAmendmentLine(
        Amendment $amendment,
        ?QuoteLine $sourceLine,
        string $description,
        int $quantity,
        string $unitPrice,
        ?string $tvaRate
    ): AmendmentLine {
        $line = new AmendmentLine();
        $line->setAmendment($amendment);
        $line->setDescription($description);
        $line->setQuantity($quantity);
        $line->setUnitPrice($unitPrice);
        if ($tvaRate !== null) {
            $line->setTvaRate($tvaRate);
        }
        
        if ($sourceLine) {
            $line->setSourceLine($sourceLine);
            $oldValue = (float) $sourceLine->getTotalHt();
            $line->setOldValue(number_format($oldValue, 2, '.', ''));
        } else {
            $line->setOldValue('0.00');
        }
        
        $line->recalculateTotalHt();
        $amendment->addLine($line);

        return $line;
    }

    private function clearTestData(SymfonyStyle $io): void
    {
        $connection = $this->entityManager->getConnection();

        try {
            // Utiliser des requêtes SQL directes pour contourner les EventSubscribers
            $connection->beginTransaction();

            // Supprimer les lignes d'avenants de test (en utilisant une jointure pour PostgreSQL)
            $connection->executeStatement(
                "DELETE FROM amendment_lines 
                 USING amendments 
                 WHERE amendment_lines.amendment_id = amendments.id 
                 AND amendments.justification LIKE 'Avenant%'"
            );

            // Supprimer les avenants de test
            $connection->executeStatement(
                "DELETE FROM amendments WHERE justification LIKE 'Avenant%'"
            );

            // Supprimer les lignes de devis de test (en utilisant une jointure pour PostgreSQL)
            $connection->executeStatement(
                "DELETE FROM quote_lines 
                 USING quotes 
                 WHERE quote_lines.quote_id = quotes.id 
                 AND quotes.numero LIKE 'DEV-TEST-%'"
            );

            // Supprimer les devis de test
            $connection->executeStatement(
                "DELETE FROM quotes WHERE numero LIKE 'DEV-TEST-%'"
            );

            $connection->commit();
            $io->success('Données de test supprimées');
        } catch (\Exception $e) {
            $connection->rollBack();
            $io->error('Erreur lors de la suppression : ' . $e->getMessage());
            throw $e;
        }
    }
}

