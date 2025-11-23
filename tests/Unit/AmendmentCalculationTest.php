<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Amendment;
use App\Entity\AmendmentLine;
use App\Entity\AmendmentStatus;
use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Entity\QuoteStatus;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour les calculs d'avenants
 * 
 * @package App\Tests\Unit
 */
class AmendmentCalculationTest extends TestCase
{
    /**
     * Test : Calcul du total HT/TTC avec TVA globale
     */
    public function testCalculateTotalsWithGlobalTva(): void
    {
        // Créer un devis avec TVA globale 20%
        $quote = new Quote();
        $quote->setTauxTVA('20.00');
        $quote->setUsePerLineTva(false);

        // Créer une ligne de devis : 100€ HT × 2 = 200€ HT
        $quoteLine = new QuoteLine();
        $quoteLine->setDescription('Produit A');
        $quoteLine->setQuantity(2);
        $quoteLine->setUnitPrice('100.00');
        $quoteLine->recalculateTotalHt();
        $quote->addLine($quoteLine);
        $quote->recalculateTotalsFromLines();

        // Créer un avenant avec TVA 20%
        $amendment = new Amendment();
        $amendment->setQuote($quote);
        $amendment->setTauxTVA('20.00');
        $amendment->setStatut(AmendmentStatus::DRAFT);

        // Créer une ligne d'avenant : modification avec ajustement de +100€
        $amendmentLine = new AmendmentLine();
        $amendmentLine->setAmendment($amendment);
        $amendmentLine->setDescription('Développement web étendu');
        $amendmentLine->setQuantity(1);
        $amendmentLine->setUnitPrice('100.00'); // Ajustement de +100€
        $amendmentLine->setSourceLine($quoteLine);
        $amendmentLine->setOldValue('200.00'); // Ancien montant HT
        $amendmentLine->recalculateTotalHt();
        $amendment->addLine($amendmentLine);

        // Recalculer les totaux de l'avenant
        $amendment->recalculateTotalsFromLines();

        // Vérifier les montants de l'avenant
        $this->assertEquals('300.00', $amendment->getMontantHT(), 'Le montant HT devrait être 300.00€ (200 + 100)');
        $this->assertEquals('60.00', $amendment->getMontantTVA(), 'La TVA devrait être 60.00€ (300 × 20%)');
        $this->assertEquals('360.00', $amendment->getMontantTTC(), 'Le montant TTC devrait être 360.00€ (300 + 60)');

        // Vérifier le Total TTC de la ligne
        $this->assertEquals('360.00', $amendmentLine->getTotalTtc(), 'Le Total TTC de la ligne devrait être 360.00€ (300 + 60)');
    }

    /**
     * Test : Calcul du total HT/TTC avec TVA par ligne
     */
    public function testCalculateTotalsWithPerLineTva(): void
    {
        // Créer un devis avec TVA par ligne
        $quote = new Quote();
        $quote->setTauxTVA('20.00');
        $quote->setUsePerLineTva(true);

        // Créer une ligne de devis avec TVA 20% : 100€ HT × 2 = 200€ HT
        $quoteLine = new QuoteLine();
        $quoteLine->setDescription('Produit A');
        $quoteLine->setQuantity(2);
        $quoteLine->setUnitPrice('100.00');
        $quoteLine->setTvaRate('20.00');
        $quoteLine->recalculateTotalHt();
        $quote->addLine($quoteLine);
        $quote->recalculateTotalsFromLines();

        // Créer un avenant
        $amendment = new Amendment();
        $amendment->setQuote($quote);
        $amendment->setTauxTVA('20.00');
        $amendment->setStatut(AmendmentStatus::DRAFT);

        // Créer une ligne d'avenant avec TVA 20% : modification avec ajustement de +100€
        $amendmentLine = new AmendmentLine();
        $amendmentLine->setAmendment($amendment);
        $amendmentLine->setDescription('Développement web étendu');
        $amendmentLine->setQuantity(1);
        $amendmentLine->setUnitPrice('100.00'); // Ajustement de +100€
        $amendmentLine->setTvaRate('20.00');
        $amendmentLine->setSourceLine($quoteLine);
        $amendmentLine->setOldValue('200.00'); // Ancien montant HT
        $amendmentLine->recalculateTotalHt();
        $amendment->addLine($amendmentLine);

        // Recalculer les totaux de l'avenant
        $amendment->recalculateTotalsFromLines();

        // Vérifier les montants de l'avenant
        $this->assertEquals('300.00', $amendment->getMontantHT(), 'Le montant HT devrait être 300.00€ (200 + 100)');
        $this->assertEquals('60.00', $amendment->getMontantTVA(), 'La TVA devrait être 60.00€ (300 × 20%)');
        $this->assertEquals('360.00', $amendment->getMontantTTC(), 'Le montant TTC devrait être 360.00€ (300 + 60)');

        // Vérifier le Total TTC de la ligne
        $this->assertEquals('360.00', $amendmentLine->getTotalTtc(), 'Le Total TTC de la ligne devrait être 360.00€ (300 + 60)');
    }

    /**
     * Test : Calcul du Total TTC avec taux de TVA de l'avenant si ligne n'a pas de taux
     */
    public function testCalculateTotalTtcWithAmendmentTvaRate(): void
    {
        // Créer un devis avec TVA globale 20%
        $quote = new Quote();
        $quote->setTauxTVA('20.00');
        $quote->setUsePerLineTva(false);

        // Créer une ligne de devis
        $quoteLine = new QuoteLine();
        $quoteLine->setDescription('Produit A');
        $quoteLine->setQuantity(2);
        $quoteLine->setUnitPrice('100.00');
        $quoteLine->recalculateTotalHt();
        $quote->addLine($quoteLine);
        $quote->recalculateTotalsFromLines();

        // Créer un avenant avec TVA 20%
        $amendment = new Amendment();
        $amendment->setQuote($quote);
        $amendment->setTauxTVA('20.00');
        $amendment->setStatut(AmendmentStatus::DRAFT);

        // Créer une ligne d'avenant SANS taux de TVA (doit utiliser celui de l'avenant)
        $amendmentLine = new AmendmentLine();
        $amendmentLine->setAmendment($amendment);
        $amendmentLine->setDescription('Nouvelle ligne');
        $amendmentLine->setQuantity(1);
        $amendmentLine->setUnitPrice('1000.00'); // Nouvelle ligne de 1000€ HT
        $amendmentLine->setTvaRate(null); // Pas de taux sur la ligne
        $amendmentLine->setOldValue('0.00');
        $amendmentLine->recalculateTotalHt();
        $amendment->addLine($amendmentLine);

        // Recalculer les totaux de l'avenant
        $amendment->recalculateTotalsFromLines();

        // Vérifier les montants
        $this->assertEquals('1000.00', $amendment->getMontantHT(), 'Le montant HT devrait être 1000.00€');
        $this->assertEquals('200.00', $amendment->getMontantTVA(), 'La TVA devrait être 200.00€ (1000 × 20%)');
        $this->assertEquals('1200.00', $amendment->getMontantTTC(), 'Le montant TTC devrait être 1200.00€ (1000 + 200)');

        // Vérifier le Total TTC de la ligne (doit utiliser le taux de l'avenant)
        $this->assertEquals('1200.00', $amendmentLine->getTotalTtc(), 'Le Total TTC de la ligne devrait être 1200.00€ (1000 + 200)');
    }

    /**
     * Test : Calcul du delta TTC
     */
    public function testCalculateDeltaTtc(): void
    {
        // Créer un devis avec TVA globale 20%
        $quote = new Quote();
        $quote->setTauxTVA('20.00');
        $quote->setUsePerLineTva(false);

        // Créer une ligne de devis : 100€ HT × 2 = 200€ HT
        $quoteLine = new QuoteLine();
        $quoteLine->setDescription('Produit A');
        $quoteLine->setQuantity(2);
        $quoteLine->setUnitPrice('100.00');
        $quoteLine->recalculateTotalHt();
        $quote->addLine($quoteLine);
        $quote->recalculateTotalsFromLines();

        // Créer un avenant
        $amendment = new Amendment();
        $amendment->setQuote($quote);
        $amendment->setTauxTVA('20.00');
        $amendment->setStatut(AmendmentStatus::DRAFT);

        // Créer une ligne d'avenant : modification avec ajustement de +100€ HT
        $amendmentLine = new AmendmentLine();
        $amendmentLine->setAmendment($amendment);
        $amendmentLine->setDescription('Développement web étendu');
        $amendmentLine->setQuantity(1);
        $amendmentLine->setUnitPrice('100.00'); // Ajustement de +100€ HT
        $amendmentLine->setSourceLine($quoteLine);
        $amendmentLine->setOldValue('200.00'); // Ancien montant HT
        $amendmentLine->recalculateTotalHt();
        $amendment->addLine($amendmentLine);

        // Vérifier le delta HT
        $this->assertEquals('100.00', $amendmentLine->getDelta(), 'Le delta HT devrait être 100.00€');

        // Vérifier le delta TTC (100€ HT + 20% TVA = 120€ TTC)
        $this->assertEquals('120.00', $amendmentLine->getDeltaTtc(), 'Le delta TTC devrait être 120.00€ (100 + 20)');
    }

    /**
     * Test : Calcul sans TVA (micro-entreprise)
     */
    public function testCalculateWithoutTva(): void
    {
        // Créer un devis sans TVA
        $quote = new Quote();
        $quote->setTauxTVA('0.00');
        $quote->setUsePerLineTva(false);

        // Créer une ligne de devis
        $quoteLine = new QuoteLine();
        $quoteLine->setDescription('Produit A');
        $quoteLine->setQuantity(2);
        $quoteLine->setUnitPrice('100.00');
        $quoteLine->recalculateTotalHt();
        $quote->addLine($quoteLine);
        $quote->recalculateTotalsFromLines();

        // Créer un avenant sans TVA
        $amendment = new Amendment();
        $amendment->setQuote($quote);
        $amendment->setTauxTVA('0.00');
        $amendment->setStatut(AmendmentStatus::DRAFT);

        // Créer une ligne d'avenant : modification avec ajustement de +100€
        $amendmentLine = new AmendmentLine();
        $amendmentLine->setAmendment($amendment);
        $amendmentLine->setDescription('Développement web étendu');
        $amendmentLine->setQuantity(1);
        $amendmentLine->setUnitPrice('100.00'); // Ajustement de +100€
        $amendmentLine->setSourceLine($quoteLine);
        $amendmentLine->setOldValue('200.00'); // Ancien montant HT
        $amendmentLine->recalculateTotalHt();
        $amendment->addLine($amendmentLine);

        // Recalculer les totaux de l'avenant
        $amendment->recalculateTotalsFromLines();

        // Vérifier les montants
        $this->assertEquals('300.00', $amendment->getMontantHT(), 'Le montant HT devrait être 300.00€');
        $this->assertEquals('0.00', $amendment->getMontantTVA(), 'La TVA devrait être 0.00€');
        $this->assertEquals('300.00', $amendment->getMontantTTC(), 'Le montant TTC devrait être 300.00€ (égal au HT)');

        // Vérifier le Total TTC de la ligne
        $this->assertEquals('300.00', $amendmentLine->getTotalTtc(), 'Le Total TTC de la ligne devrait être 300.00€ (égal au HT)');
    }

    /**
     * Test : Calcul du total corrigé du devis après avenant
     */
    public function testCalculateCorrectedTotal(): void
    {
        // Créer un devis avec TVA globale 20%
        $quote = new Quote();
        $quote->setTauxTVA('20.00');
        $quote->setUsePerLineTva(false);
        $quote->setStatut(QuoteStatus::SIGNED);

        // Créer une ligne de devis : 100€ HT × 2 = 200€ HT → 240€ TTC
        $quoteLine = new QuoteLine();
        $quoteLine->setDescription('Produit A');
        $quoteLine->setQuantity(2);
        $quoteLine->setUnitPrice('100.00');
        $quoteLine->recalculateTotalHt();
        $quote->addLine($quoteLine);
        $quote->recalculateTotalsFromLines();

        // Vérifier le montant initial
        $this->assertEquals('240.00', $quote->getMontantTTC(), 'Le montant TTC initial devrait être 240.00€');

        // Créer un avenant avec ajustement de +100€ HT
        $amendment = new Amendment();
        $amendment->setQuote($quote);
        $amendment->setTauxTVA('20.00');
        $amendment->setStatut(AmendmentStatus::DRAFT); // D'abord en DRAFT

        $amendmentLine = new AmendmentLine();
        $amendmentLine->setAmendment($amendment);
        $amendmentLine->setDescription('Développement web étendu');
        $amendmentLine->setQuantity(1);
        $amendmentLine->setUnitPrice('100.00'); // Ajustement de +100€ HT
        $amendmentLine->setSourceLine($quoteLine);
        $amendmentLine->setOldValue('200.00');
        $amendmentLine->recalculateTotalHt();
        $amendment->addLine($amendmentLine);
        $amendment->recalculateTotalsFromLines();

        // Maintenant on peut passer en SIGNED (après avoir ajouté les lignes)
        $amendment->setStatut(AmendmentStatus::SIGNED);

        // Ajouter l'avenant au devis (simulation)
        // Note: Dans la vraie application, cela se fait via la relation Doctrine

        // Vérifier le total corrigé (240€ initial + 120€ delta TTC = 360€)
        // Le delta TTC est calculé depuis le delta HT : 100€ HT + 20% = 120€ TTC
        $deltaTtc = (float) $amendmentLine->getDeltaTtc();
        $expectedTotal = (float) $quote->getMontantTTC() + $deltaTtc;
        
        $this->assertEquals(120.00, $deltaTtc, 'Le delta TTC devrait être 120.00€');
        $this->assertEquals(360.00, $expectedTotal, 'Le total corrigé devrait être 360.00€ (240 + 120)');
    }
}

