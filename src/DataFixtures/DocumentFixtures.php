<?php

namespace App\DataFixtures;

use App\Entity\Amendment;
use App\Entity\AmendmentLine;
use App\Entity\AmendmentStatus;
use App\Entity\CreditNote;
use App\Entity\CreditNoteLine;
use App\Entity\CreditNoteStatus;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\InvoiceStatus;
use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Entity\QuoteStatus;
use App\Repository\ClientRepository;
use App\Repository\CompanySettingsRepository;
use App\Repository\TariffRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

/**
 * Fixtures pour les documents commerciaux : Devis, Factures, Avenants, Avoirs.
 *
 * Utilise des tarifs du catalogue ET des lignes personnalisées pour tester le système complet.
 */
class DocumentFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public function __construct(
        private ClientRepository $clientRepository,
        private TariffRepository $tariffRepository,
        private CompanySettingsRepository $companySettingsRepository
    ) {}

    public static function getGroups(): array
    {
        return ['DocumentFixtures'];
    }

    public function getDependencies(): array
    {
        return [
            ClientFixtures::class,
            TariffFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        // Récupérer le company_id (basé sur l'email de l'utilisateur réel)
        $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
        $companyId = Uuid::v5($namespace, 'sylvain.schmitt70@gmail.com')->toString();

        // Récupérer les clients et tarifs existants
        $clients = $this->clientRepository->findAll();
        $tariffs = $this->tariffRepository->findBy(['actif' => true]);

        if (empty($clients) || empty($tariffs)) {
            throw new \RuntimeException('Veuillez d\'abord charger ClientFixtures et TariffFixtures');
        }

        // Prendre les 5 premiers clients pour les documents
        $clients = array_slice($clients, 0, 5);

        // Index des tarifs par catégorie
        $tariffsByCategory = [];
        foreach ($tariffs as $tariff) {
            $tariffsByCategory[$tariff->getCategorie()][] = $tariff;
        }

        // ===== DEVIS =====
        $quotes = [];

        // Utiliser les tarifs directement (avec indices sécurisés)
        $siteTariff = $tariffs[0] ?? null;
        $site2Tariff = $tariffs[1] ?? $siteTariff;
        $site3Tariff = $tariffs[2] ?? $siteTariff;
        $maintenanceTariff = null;
        $reservationTariff = null;
        $appTariff = null;

        foreach ($tariffs as $t) {
            if ($t->getCategorie() === 'maintenance') $maintenanceTariff = $t;
            if ($t->getCategorie() === 'reservation') $reservationTariff = $t;
            if ($t->getCategorie() === 'application') $appTariff = $t;
        }
        $maintenanceTariff = $maintenanceTariff ?? $siteTariff;
        $reservationTariff = $reservationTariff ?? $siteTariff;
        $appTariff = $appTariff ?? $siteTariff;

        // Devis 1: DRAFT - Site vitrine avec tarifs du catalogue
        $quote1 = $this->createQuote($manager, $clients[0], $companyId, QuoteStatus::DRAFT, 'DEV-2025-001', [
            ['tariff' => $siteTariff, 'quantity' => 1],
            ['tariff' => null, 'quantity' => 1, 'description' => 'Formation utilisateur (2h)', 'unitPrice' => 150.00],
        ]);
        $quotes['draft'] = $quote1;

        // Devis 2: SENT - Réservation avec mix tarif + custom
        $quote2 = $this->createQuote($manager, $clients[1], $companyId, QuoteStatus::SENT, 'DEV-2025-002', [
            ['tariff' => $reservationTariff, 'quantity' => 1],
            ['tariff' => null, 'quantity' => 5, 'description' => 'Support technique (heures)', 'unitPrice' => 80.00],
        ]);
        $quotes['sent'] = $quote2;

        // Devis 3: SIGNED - Application CRM avec maintenance
        $quote3 = $this->createQuote($manager, $clients[2], $companyId, QuoteStatus::SIGNED, 'DEV-2025-003', [
            ['tariff' => $appTariff, 'quantity' => 1],
            ['tariff' => $maintenanceTariff, 'quantity' => 12], // 12 mois
            ['tariff' => null, 'quantity' => 1, 'description' => 'Migration des données existantes', 'unitPrice' => 500.00],
        ]);
        $quote3->setDateSignature(new \DateTime('-10 days'));
        $quote3->setSignatureClient('Jean Dupont');
        $quotes['signed'] = $quote3;

        // Devis 4: SIGNED - Site e-commerce (pour créer une facture)
        $quote4 = $this->createQuote($manager, $clients[3], $companyId, QuoteStatus::SIGNED, 'DEV-2025-004', [
            ['tariff' => $site3Tariff, 'quantity' => 1],
            ['tariff' => null, 'quantity' => 1, 'description' => 'Intégration paiement Stripe', 'unitPrice' => 350.00],
            ['tariff' => null, 'quantity' => 1, 'description' => 'Configuration livraison Colissimo', 'unitPrice' => 200.00],
        ]);
        $quote4->setDateSignature(new \DateTime('-30 days'));
        $quote4->setSignatureClient('Marie Martin');
        $quotes['signed_for_invoice'] = $quote4;

        $manager->flush();

        // ===== FACTURES =====
        $invoices = [];

        // Créer une facture depuis le devis 4 (SIGNED)
        $invoice1 = $this->createInvoiceFromQuote($manager, $quote4, InvoiceStatus::ISSUED, 'FACT-2025-001');
        $invoice1->setDateCreation(new \DateTime('-25 days'));
        $invoice1->setDateEcheance(new \DateTime('+5 days'));
        $invoices['issued'] = $invoice1;

        // Facture 2: SENT (prête à être payée)
        $invoice2 = $this->createInvoice($manager, $clients[4], $companyId, InvoiceStatus::SENT, 'FACT-2025-002', [
            ['tariff' => $maintenanceTariff, 'quantity' => 6], // 6 mois maintenance
            ['tariff' => null, 'quantity' => 3, 'description' => 'Intervention urgente (heures)', 'unitPrice' => 120.00],
        ]);
        $invoice2->setDateCreation(new \DateTime('-15 days'));
        $invoice2->setDateEcheance(new \DateTime('+15 days'));
        $invoices['sent'] = $invoice2;

        // Facture 3: PAID
        $invoice3 = $this->createInvoice($manager, $clients[0], $companyId, InvoiceStatus::PAID, 'FACT-2025-003', [
            ['tariff' => $site2Tariff, 'quantity' => 1], // Site premium
        ]);
        $invoice3->setDateCreation(new \DateTime('-45 days'));
        $invoice3->setDateEcheance(new \DateTime('-15 days'));
        $invoice3->setDatePaiement(new \DateTime('-10 days'));
        $invoices['paid'] = $invoice3;

        $manager->flush();

        // ===== AVENANTS =====
        // Avenant sur le devis signé (quote3) - Ajout de fonctionnalités
        $amendment1 = $this->createAmendment($manager, $quote3, AmendmentStatus::DRAFT, null, [
            ['tariff' => null, 'quantity' => 1, 'description' => 'Module export PDF personnalisé', 'unitPrice' => 400.00],
            ['tariff' => null, 'quantity' => 1, 'description' => 'Intégration API externe', 'unitPrice' => 600.00],
        ]);
        $amendment1->setMotif('Ajout de fonctionnalités demandées par le client');
        $amendment1->setModifications('Module export PDF et intégration API');

        // Avenant 2: SIGNED sur quote4
        $amendment2 = $this->createAmendment($manager, $quote4, AmendmentStatus::SIGNED, 'AV-2025-001', [
            ['tariff' => null, 'quantity' => 1, 'description' => 'Ajout module newsletter', 'unitPrice' => 300.00],
        ]);
        $amendment2->setMotif('Extension des fonctionnalités e-commerce');
        $amendment2->setModifications('Ajout d\'un module newsletter avec gestion des abonnés');
        $amendment2->setDateSignature(new \DateTime('-5 days'));
        $amendment2->setSignatureClient('Marie Martin');

        $manager->flush();

        // ===== AVOIRS =====
        // Avoir partiel sur la facture payée (invoice3)
        $creditNote1 = $this->createCreditNote($manager, $invoice3, CreditNoteStatus::DRAFT, null, [
            ['quantity' => 1, 'description' => 'Remise exceptionnelle fidélité', 'unitPrice' => -150.00],
        ]);
        $creditNote1->setReason('Remise accordée pour fidélité client');

        // Avoir émis sur la facture envoyée (invoice2)
        $creditNote2 = $this->createCreditNote($manager, $invoice2, CreditNoteStatus::ISSUED, 'AV-2025-001', [
            ['quantity' => 1, 'description' => 'Correction erreur facturation', 'unitPrice' => -60.00],
        ]);
        $creditNote2->setReason('Erreur sur le nombre d\'heures facturées');
        $creditNote2->setDateEmission(new \DateTime('-2 days'));

        $manager->flush();
    }

    private function createQuote(ObjectManager $manager, $client, string $companyId, QuoteStatus $status, string $numero, array $lines): Quote
    {
        $quote = new Quote();
        $quote->setNumero($numero);
        $quote->setClient($client);
        $quote->setCompanyId($companyId);
        $quote->setStatut($status);
        $quote->setDateValidite(new \DateTime('+30 days'));
        $quote->setTauxTVA('0.00'); // Micro-entrepreneur
        $quote->setConditionsPaiement('Paiement à 30 jours');
        $quote->setDelaiLivraison('4-6 semaines');

        foreach ($lines as $lineData) {
            $line = new QuoteLine();
            $line->setQuote($quote);

            if ($lineData['tariff']) {
                $line->setTariff($lineData['tariff']);
                $line->setDescription($lineData['tariff']->getNom());
                $line->setUnitPrice($lineData['tariff']->getPrix());
                $line->setIsCustom(false);
            } else {
                $line->setDescription($lineData['description']);
                $line->setUnitPrice((string) $lineData['unitPrice']);
                $line->setIsCustom(true);
            }

            $line->setQuantity($lineData['quantity']);
            $line->setTvaRate('0.00');
            $line->recalculateTotalHt();

            $quote->addLine($line);
            $manager->persist($line);
        }

        $quote->recalculateTotalsFromLines();
        $manager->persist($quote);

        return $quote;
    }

    private function createInvoice(ObjectManager $manager, $client, string $companyId, InvoiceStatus $status, string $numero, array $lines): Invoice
    {
        $invoice = new Invoice();
        $invoice->setNumero($numero);
        $invoice->setClient($client);
        $invoice->setCompanyId($companyId);
        $invoice->setStatut($status->value);
        $invoice->setDateEcheance(new \DateTime('+30 days'));
        $invoice->setConditionsPaiement('Paiement à 30 jours');
        $invoice->setDelaiPaiement(30);
        $invoice->setPenalitesRetard('3.00');

        foreach ($lines as $lineData) {
            $line = new InvoiceLine();
            $line->setInvoice($invoice);

            if ($lineData['tariff']) {
                $line->setTariff($lineData['tariff']);
                $line->setDescription($lineData['tariff']->getNom());
                $line->setUnitPrice($lineData['tariff']->getPrix());
            } else {
                $line->setDescription($lineData['description']);
                $line->setUnitPrice((string) $lineData['unitPrice']);
            }

            $line->setQuantity($lineData['quantity']);
            $line->setTvaRate('0.00');
            $line->recalculateTotalHt();

            $invoice->addLine($line);
            $manager->persist($line);
        }

        $invoice->recalculateTotalsFromLines();
        $manager->persist($invoice);

        return $invoice;
    }

    private function createInvoiceFromQuote(ObjectManager $manager, Quote $quote, InvoiceStatus $status, string $numero): Invoice
    {
        $invoice = new Invoice();
        $invoice->setNumero($numero);
        $invoice->setQuote($quote);
        $invoice->setClient($quote->getClient());
        $invoice->setCompanyId($quote->getCompanyId());
        $invoice->setStatut($status->value);
        $invoice->setDateEcheance(new \DateTime('+30 days'));
        $invoice->setConditionsPaiement($quote->getConditionsPaiement());
        $invoice->setDelaiPaiement(30);
        $invoice->setPenalitesRetard('3.00');

        // Copier les lignes du devis
        foreach ($quote->getLines() as $quoteLine) {
            $line = new InvoiceLine();
            $line->setInvoice($invoice);
            $line->setTariff($quoteLine->getTariff());
            $line->setDescription($quoteLine->getDescription());
            $line->setQuantity($quoteLine->getQuantity());
            $line->setUnitPrice($quoteLine->getUnitPrice());
            $line->setTvaRate($quoteLine->getTvaRate());
            $line->recalculateTotalHt();

            $invoice->addLine($line);
            $manager->persist($line);
        }

        $invoice->recalculateTotalsFromLines();
        $manager->persist($invoice);

        return $invoice;
    }

    private function createAmendment(ObjectManager $manager, Quote $quote, AmendmentStatus $status, ?string $numero, array $lines): Amendment
    {
        $amendment = new Amendment();
        $amendment->setQuote($quote);
        $amendment->setCompanyId($quote->getCompanyId());
        // Créer en DRAFT d'abord pour éviter la validation prématurée
        $amendment->setStatut(AmendmentStatus::DRAFT);
        $amendment->setTauxTVA($quote->getTauxTVA());

        if ($numero) {
            $amendment->setNumero($numero);
        }

        foreach ($lines as $lineData) {
            $line = new AmendmentLine();
            $line->setAmendment($amendment);
            $line->setDescription($lineData['description']);
            $line->setQuantity($lineData['quantity']);
            $line->setUnitPrice((string) $lineData['unitPrice']);
            $line->setOldValue('0.00'); // Nouvelle ligne ajoutée
            $line->setTvaRate('0.00');
            $line->recalculateTotalHt();

            $amendment->addLine($line);
            $manager->persist($line);
        }

        $amendment->recalculateTotalsFromLines();

        // Maintenant que les lignes sont ajoutées, on peut changer le statut
        $amendment->setStatut($status);

        $manager->persist($amendment);

        return $amendment;
    }

    private function createCreditNote(ObjectManager $manager, Invoice $invoice, CreditNoteStatus $status, ?string $numero, array $lines): CreditNote
    {
        $creditNote = new CreditNote();
        $creditNote->setInvoice($invoice);
        $creditNote->setCompanyId($invoice->getCompanyId());
        $creditNote->setStatut($status);

        if ($numero) {
            $creditNote->setNumber($numero);
        }

        foreach ($lines as $lineData) {
            $line = new CreditNoteLine();
            $line->setCreditNote($creditNote);
            $line->setDescription($lineData['description']);
            $line->setQuantity($lineData['quantity']);
            $line->setUnitPrice((string) $lineData['unitPrice']); // Montant négatif pour un avoir
            $line->setTvaRate('0.00');
            $line->recalculateTotalHt();

            $creditNote->addLine($line);
            $manager->persist($line);
        }

        $creditNote->recalculateTotals();
        $manager->persist($creditNote);

        return $creditNote;
    }
}
