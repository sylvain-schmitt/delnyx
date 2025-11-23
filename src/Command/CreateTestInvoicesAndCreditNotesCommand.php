<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\InvoiceStatus;
use App\Entity\CreditNote;
use App\Entity\CreditNoteLine;
use App\Entity\CreditNoteStatus;
use App\Entity\Quote;
use App\Entity\QuoteStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:create-test-invoices-credit-notes',
    description: 'Crée des factures et avoirs de test pour vérifier les calculs et l\'affichage'
)]
class CreateTestInvoicesAndCreditNotesCommand extends Command
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
            'Email de l\'utilisateur connecté pour générer le companyId (par défaut: devil.9068@gmail.com)',
            'devil.9068@gmail.com'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('clear')) {
            $io->warning('Suppression des données de test existantes...');
            $this->clearTestData($io);
        }

        $io->title('Création de factures et avoirs de test');

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

        // Company ID : utiliser l'email de l'utilisateur connecté
        $userEmail = $input->getOption('user-email') ?: 'devil.9068@gmail.com';
        $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
        $companyId = Uuid::v5($namespace, $userEmail)->toString();
        $io->note('Company ID utilisé (basé sur l\'email utilisateur : ' . $userEmail . ') : ' . $companyId);

        // Récupérer ou créer des devis signés pour créer des factures
        $quoteRepository = $this->entityManager->getRepository(Quote::class);
        $quotes = $quoteRepository->createQueryBuilder('q')
            ->where('q.companyId = :companyId')
            ->andWhere('q.statut = :signed')
            ->andWhere('q.invoice IS NULL')
            ->setParameter('companyId', $companyId)
            ->setParameter('signed', QuoteStatus::SIGNED)
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        if (empty($quotes)) {
            $io->warning('Aucun devis signé sans facture trouvé. Créez d\'abord des devis avec la commande app:create-test-quotes-amendments');
            return Command::FAILURE;
        }

        $io->info('Devis disponibles : ' . count($quotes));

        // 1. Facture depuis un devis avec TVA globale
        if (isset($quotes[0])) {
            $quote1 = $quotes[0];
            $invoice1 = $this->createInvoiceFromQuote($quote1, $companyId, InvoiceStatus::ISSUED);
            $io->success('Facture 1 créée depuis devis ' . $quote1->getNumero() . ' : TVA globale, Total = ' . $invoice1->getMontantTTCFormate());

            // Créer un avoir pour cette facture
            $creditNote1 = $this->createCreditNote($invoice1, $companyId, CreditNoteStatus::ISSUED, 'Remise exceptionnelle');
            $invoiceLine1 = $invoice1->getLines()->first();
            if ($invoiceLine1) {
                $this->addCreditNoteLine($creditNote1, $invoiceLine1, 'Remise', 1, '-50.00', $quote1->getTauxTVA());
            }
            $this->entityManager->persist($creditNote1);
            $this->entityManager->flush();
            $creditNote1->recalculateTotals();
            $this->entityManager->flush();
            $io->success('Avoir 1 créé : Remise de 50€ HT');
        }

        // 2. Facture depuis un devis avec TVA par ligne
        if (isset($quotes[1])) {
            $quote2 = $quotes[1];
            $invoice2 = $this->createInvoiceFromQuote($quote2, $companyId, InvoiceStatus::ISSUED);
            $io->success('Facture 2 créée depuis devis ' . $quote2->getNumero() . ' : TVA par ligne, Total = ' . $invoice2->getMontantTTCFormate());
        }

        // 3. Facture depuis un devis sans TVA
        if (isset($quotes[2])) {
            $quote3 = $quotes[2];
            $invoice3 = $this->createInvoiceFromQuote($quote3, $companyId, InvoiceStatus::ISSUED);
            $io->success('Facture 3 créée depuis devis ' . $quote3->getNumero() . ' : Sans TVA, Total = ' . $invoice3->getMontantTTCFormate());
        }

        // 4. Facture créée directement (sans devis) avec TVA
        $invoice4 = $this->createInvoiceDirectly(
            $client,
            $companyId,
            InvoiceStatus::DRAFT,
            '20.00',
            'Facture directe avec TVA 20%'
        );
        $this->addInvoiceLine($invoice4, 'Service personnalisé', 2, '150.00', '20.00');
        $this->entityManager->persist($invoice4);
        $this->entityManager->flush();
        $invoice4->recalculateTotalsFromLines();
        $this->entityManager->flush();
        $io->success('Facture 4 créée directement : TVA 20%, Total = ' . $invoice4->getMontantTTCFormate());

        // 5. Facture créée directement sans TVA
        $invoice5 = $this->createInvoiceDirectly(
            $client,
            $companyId,
            InvoiceStatus::DRAFT,
            '0.00',
            'Facture directe sans TVA'
        );
        $this->addInvoiceLine($invoice5, 'Service micro-entreprise', 1, '500.00', null);
        $this->entityManager->persist($invoice5);
        $this->entityManager->flush();
        $invoice5->recalculateTotalsFromLines();
        $this->entityManager->flush();
        $io->success('Facture 5 créée directement : Sans TVA, Total = ' . $invoice5->getMontantTTCFormate());

        $io->success('✅ Toutes les factures et avoirs de test ont été créés avec succès !');
        return Command::SUCCESS;
    }

    private function createInvoiceFromQuote(
        Quote $quote,
        string $companyId,
        InvoiceStatus $statut
    ): Invoice {
        $invoice = new Invoice();
        $invoice->setClient($quote->getClient());
        $invoice->setQuote($quote);
        $invoice->setCompanyId($companyId);
        $invoice->setStatutEnum($statut);
        $invoice->setDateCreation(new \DateTime());
        $invoice->setDateModification(new \DateTime());

        // Date d'échéance (30 jours par défaut)
        $dateEcheance = new \DateTime();
        $dateEcheance->modify('+30 days');
        $invoice->setDateEcheance($dateEcheance);

        // Copier les conditions de paiement
        if ($quote->getConditionsPaiement()) {
            $invoice->setConditionsPaiement($quote->getConditionsPaiement());
        }

        // Copier le montant d'accompte
        if ($quote->getMontantAcompte()) {
            $invoice->setMontantAcompte($quote->getMontantAcompte());
        }

        // Copier les lignes du devis
        foreach ($quote->getLines() as $quoteLine) {
            $invoiceLine = new InvoiceLine();
            $invoiceLine->setDescription($quoteLine->getDescription());
            $invoiceLine->setQuantity($quoteLine->getQuantity());
            $invoiceLine->setUnitPrice($quoteLine->getUnitPrice());
            $invoiceLine->setTotalHt($quoteLine->getTotalHt());
            $invoiceLine->setTvaRate($quoteLine->getTvaRate());
            $invoiceLine->setTariff($quoteLine->getTariff());
            $invoice->addLine($invoiceLine);
        }

        // Recalculer les totaux
        $invoice->recalculateTotalsFromLines();

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $invoice;
    }

    private function createInvoiceDirectly(
        Client $client,
        string $companyId,
        InvoiceStatus $statut,
        string $tauxTVA,
        string $notes
    ): Invoice {
        $invoice = new Invoice();
        $invoice->setClient($client);
        $invoice->setCompanyId($companyId);
        $invoice->setStatutEnum($statut);
        $invoice->setDateCreation(new \DateTime());
        $invoice->setDateModification(new \DateTime());
        $invoice->setNotes($notes);

        // Date d'échéance (30 jours par défaut)
        $dateEcheance = new \DateTime();
        $dateEcheance->modify('+30 days');
        $invoice->setDateEcheance($dateEcheance);

        return $invoice;
    }

    private function addInvoiceLine(
        Invoice $invoice,
        string $description,
        int $quantity,
        string $unitPrice,
        ?string $tvaRate
    ): InvoiceLine {
        $line = new InvoiceLine();
        $line->setInvoice($invoice);
        $line->setDescription($description);
        $line->setQuantity($quantity);
        $line->setUnitPrice($unitPrice);
        if ($tvaRate !== null) {
            $line->setTvaRate($tvaRate);
        }
        $line->recalculateTotalHt();
        $invoice->addLine($line);

        return $line;
    }

    private function createCreditNote(
        Invoice $invoice,
        string $companyId,
        CreditNoteStatus $statut,
        string $reason
    ): CreditNote {
        $creditNote = new CreditNote();
        $creditNote->setInvoice($invoice);
        $creditNote->setCompanyId($companyId);
        $creditNote->setStatutEnum($statut);
        $creditNote->setReason($reason);
        $creditNote->setDateCreation(new \DateTime());
        $creditNote->setDateModification(new \DateTime());

        if ($statut === CreditNoteStatus::ISSUED) {
            $creditNote->setDateEmission(new \DateTime());
        }

        return $creditNote;
    }

    private function addCreditNoteLine(
        CreditNote $creditNote,
        InvoiceLine $sourceLine,
        string $description,
        int $quantity,
        string $unitPrice,
        ?string $tvaRate
    ): CreditNoteLine {
        $line = new CreditNoteLine();
        $line->setCreditNote($creditNote);
        $line->setDescription($description);
        $line->setQuantity($quantity);
        $line->setUnitPrice($unitPrice);
        if ($tvaRate !== null) {
            $line->setTvaRate($tvaRate);
        }
        $line->setSourceLine($sourceLine);
        $line->setOldValue($sourceLine->getTotalHt());
        
        // Calculer newValue et delta
        $oldValue = (float) $sourceLine->getTotalHt();
        $delta = (float) $unitPrice * $quantity;
        $newValue = $oldValue + $delta;
        
        $line->setNewValue(number_format($newValue, 2, '.', ''));
        $line->setDelta(number_format($delta, 2, '.', ''));
        $line->setTotalHt(number_format($newValue, 2, '.', ''));
        
        $creditNote->addLine($line);

        return $line;
    }

    private function clearTestData(SymfonyStyle $io): void
    {
        $connection = $this->entityManager->getConnection();

        try {
            // Utiliser des requêtes SQL directes pour contourner les EventSubscribers
            $connection->beginTransaction();

            // Supprimer les lignes d'avoirs de test
            $connection->executeStatement(
                "DELETE FROM credit_note_lines 
                 USING credit_notes 
                 WHERE credit_note_lines.credit_note_id = credit_notes.id 
                 AND (credit_notes.reason LIKE 'Remise%' OR credit_notes.reason LIKE 'Facture directe%')"
            );

            // Supprimer les avoirs de test
            $connection->executeStatement(
                "DELETE FROM credit_notes WHERE reason LIKE 'Remise%' OR reason LIKE 'Facture directe%'"
            );

            // Supprimer les lignes de factures de test
            $connection->executeStatement(
                "DELETE FROM invoice_lines 
                 USING invoices 
                 WHERE invoice_lines.invoice_id = invoices.id 
                 AND (invoices.notes LIKE 'Facture directe%' OR invoices.notes LIKE 'Facture%test%')"
            );

            // Supprimer les factures de test (mais pas celles créées depuis des devis)
            $connection->executeStatement(
                "DELETE FROM invoices WHERE notes LIKE 'Facture directe%' OR notes LIKE 'Facture%test%'"
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

