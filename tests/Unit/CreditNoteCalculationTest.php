<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\CreditNote;
use App\Entity\CreditNoteLine;
use App\Entity\CreditNoteStatus;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\InvoiceStatus;
use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Entity\QuoteStatus;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour les calculs d'avoirs
 * 
 * @package App\Tests\Unit
 */
class CreditNoteCalculationTest extends TestCase
{
    /**
     * Test : Calcul du total HT/TTC avec TVA
     */
    public function testCalculateTotalsWithTva(): void
    {
        // Créer un devis et une facture émise
        $quote = new Quote();
        $quote->setTauxTVA('20.00');
        $quote->setUsePerLineTva(false);
        $quote->setStatut(QuoteStatus::SIGNED);

        $quoteLine = new QuoteLine();
        $quoteLine->setDescription('Produit A');
        $quoteLine->setQuantity(2);
        $quoteLine->setUnitPrice('100.00');
        $quoteLine->recalculateTotalHt();
        $quote->addLine($quoteLine);
        $quote->recalculateTotalsFromLines();

        $invoice = new Invoice();
        $invoice->setQuote($quote);
        $invoice->setStatutEnum(InvoiceStatus::ISSUED);

        $invoiceLine = new InvoiceLine();
        $invoiceLine->setDescription('Produit A');
        $invoiceLine->setQuantity(2);
        $invoiceLine->setUnitPrice('100.00');
        $invoiceLine->setTotalHt('200.00');
        $invoiceLine->setTvaRate('20.00');
        $invoice->addLine($invoiceLine);
        $invoice->recalculateTotalsFromLines();

        // Créer un avoir avec TVA 20%
        $creditNote = new CreditNote();
        $creditNote->setInvoice($invoice);
        $creditNote->setStatutEnum(CreditNoteStatus::DRAFT);
        $creditNote->setReason('Erreur de facturation');

        // Créer une ligne d'avoir : -50€ HT → -60€ TTC (avec 20% TVA)
        $creditNoteLine = new CreditNoteLine();
        $creditNoteLine->setCreditNote($creditNote);
        $creditNoteLine->setDescription('Remise');
        $creditNoteLine->setQuantity(1);
        $creditNoteLine->setUnitPrice('-50.00');
        $creditNoteLine->setTvaRate('20.00');
        $creditNoteLine->setOldValue('0.00');
        $creditNoteLine->setNewValue('-50.00');
        $creditNoteLine->setDelta('-50.00');
        $creditNoteLine->setTotalHt('-50.00');
        $creditNote->addLine($creditNoteLine);

        // Recalculer les totaux de l'avoir
        $creditNote->recalculateTotals();

        // Vérifier les montants
        $this->assertEquals('-50.00', $creditNote->getMontantHT(), 'Le montant HT devrait être -50.00€');
        $this->assertEquals('-10.00', $creditNote->getMontantTVA(), 'La TVA devrait être -10.00€ (-50 × 20%)');
        $this->assertEquals('-60.00', $creditNote->getMontantTTC(), 'Le montant TTC devrait être -60.00€ (-50 - 10)');
    }

    /**
     * Test : Calcul du total HT/TTC sans TVA
     */
    public function testCalculateTotalsWithoutTva(): void
    {
        // Créer un devis et une facture émise sans TVA
        $quote = new Quote();
        $quote->setTauxTVA('0.00');
        $quote->setUsePerLineTva(false);
        $quote->setStatut(QuoteStatus::SIGNED);

        $invoice = new Invoice();
        $invoice->setQuote($quote);
        $invoice->setStatutEnum(InvoiceStatus::ISSUED);

        // Créer un avoir sans TVA
        $creditNote = new CreditNote();
        $creditNote->setInvoice($invoice);
        $creditNote->setStatut(CreditNoteStatus::DRAFT);
        $creditNote->setReason('Remise');

        // Créer une ligne d'avoir : -50€ HT → -50€ TTC (sans TVA)
        $creditNoteLine = new CreditNoteLine();
        $creditNoteLine->setCreditNote($creditNote);
        $creditNoteLine->setDescription('Remise');
        $creditNoteLine->setQuantity(1);
        $creditNoteLine->setUnitPrice('-50.00');
        $creditNoteLine->setTvaRate(null);
        $creditNoteLine->setOldValue('0.00');
        $creditNoteLine->setNewValue('-50.00');
        $creditNoteLine->setDelta('-50.00');
        $creditNoteLine->setTotalHt('-50.00');
        $creditNote->addLine($creditNoteLine);

        // Recalculer les totaux
        $creditNote->recalculateTotals();

        // Vérifier les montants
        $this->assertEquals('-50.00', $creditNote->getMontantHT(), 'Le montant HT devrait être -50.00€');
        $this->assertEquals('0.00', $creditNote->getMontantTVA(), 'La TVA devrait être 0.00€');
        $this->assertEquals('-50.00', $creditNote->getMontantTTC(), 'Le montant TTC devrait être -50.00€ (égal au HT)');
    }

    /**
     * Test : Calcul avec TVA par ligne
     */
    public function testCalculateTotalsWithPerLineTva(): void
    {
        // Créer un devis et une facture émise avec TVA par ligne
        $quote = new Quote();
        $quote->setTauxTVA('20.00');
        $quote->setUsePerLineTva(true);
        $quote->setStatut(QuoteStatus::SIGNED);

        $invoice = new Invoice();
        $invoice->setQuote($quote);
        $invoice->setStatutEnum(InvoiceStatus::ISSUED);

        // Créer un avoir avec TVA par ligne
        $creditNote = new CreditNote();
        $creditNote->setInvoice($invoice);
        $creditNote->setStatut(CreditNoteStatus::DRAFT);
        $creditNote->setReason('Remise');

        // Créer une ligne avec TVA 20% : -50€ HT → -60€ TTC
        $creditNoteLine1 = new CreditNoteLine();
        $creditNoteLine1->setCreditNote($creditNote);
        $creditNoteLine1->setDescription('Remise 20%');
        $creditNoteLine1->setQuantity(1);
        $creditNoteLine1->setUnitPrice('-50.00');
        $creditNoteLine1->setTvaRate('20.00');
        $creditNoteLine1->setOldValue('0.00');
        $creditNoteLine1->setNewValue('-50.00');
        $creditNoteLine1->setDelta('-50.00');
        $creditNoteLine1->setTotalHt('-50.00');
        $creditNote->addLine($creditNoteLine1);

        // Créer une ligne avec TVA 10% : -30€ HT → -33€ TTC
        $creditNoteLine2 = new CreditNoteLine();
        $creditNoteLine2->setCreditNote($creditNote);
        $creditNoteLine2->setDescription('Remise 10%');
        $creditNoteLine2->setQuantity(1);
        $creditNoteLine2->setUnitPrice('-30.00');
        $creditNoteLine2->setTvaRate('10.00');
        $creditNoteLine2->setOldValue('0.00');
        $creditNoteLine2->setNewValue('-30.00');
        $creditNoteLine2->setDelta('-30.00');
        $creditNoteLine2->setTotalHt('-30.00');
        $creditNote->addLine($creditNoteLine2);

        // Recalculer les totaux
        $creditNote->recalculateTotals();

        // Vérifier les montants
        $this->assertEquals('-80.00', $creditNote->getMontantHT(), 'Le montant HT devrait être -80.00€');
        $this->assertEquals('-13.00', $creditNote->getMontantTVA(), 'La TVA devrait être -13.00€ (-10 - 3)');
        $this->assertEquals('-93.00', $creditNote->getMontantTTC(), 'Le montant TTC devrait être -93.00€ (-80 - 13)');
    }
}

