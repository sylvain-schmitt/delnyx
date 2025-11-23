<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Entity\QuoteStatus;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour les calculs de devis
 * 
 * @package App\Tests\Unit
 */
class QuoteCalculationTest extends TestCase
{
    /**
     * Test : Calcul du total HT avec TVA globale
     */
    public function testCalculateTotalHtWithGlobalTva(): void
    {
        $quote = new Quote();
        $quote->setTauxTVA('20.00');
        $quote->setUsePerLineTva(false);

        // Créer une ligne : 100€ HT × 2 = 200€ HT
        $line1 = new QuoteLine();
        $line1->setDescription('Produit A');
        $line1->setQuantity(2);
        $line1->setUnitPrice('100.00');
        $line1->recalculateTotalHt();
        $quote->addLine($line1);

        // Créer une autre ligne : 50€ HT × 3 = 150€ HT
        $line2 = new QuoteLine();
        $line2->setDescription('Produit B');
        $line2->setQuantity(3);
        $line2->setUnitPrice('50.00');
        $line2->recalculateTotalHt();
        $quote->addLine($line2);

        // Recalculer les totaux
        $quote->recalculateTotalsFromLines();

        // Vérifier les montants
        $this->assertEquals('350.00', $quote->getMontantHT(), 'Le montant HT devrait être 350.00€');
        $this->assertEquals('70.00', $quote->getMontantTVA(), 'La TVA devrait être 70.00€ (350 × 20%)');
        $this->assertEquals('420.00', $quote->getMontantTTC(), 'Le montant TTC devrait être 420.00€ (350 + 70)');
    }

    /**
     * Test : Calcul du total HT avec TVA par ligne
     */
    public function testCalculateTotalHtWithPerLineTva(): void
    {
        $quote = new Quote();
        $quote->setTauxTVA('20.00');
        $quote->setUsePerLineTva(true);

        // Créer une ligne avec TVA 20% : 100€ HT × 2 = 200€ HT → 240€ TTC
        $line1 = new QuoteLine();
        $line1->setDescription('Produit A');
        $line1->setQuantity(2);
        $line1->setUnitPrice('100.00');
        $line1->setTvaRate('20.00');
        $line1->recalculateTotalHt();
        $quote->addLine($line1);

        // Créer une ligne avec TVA 10% : 50€ HT × 3 = 150€ HT → 165€ TTC
        $line2 = new QuoteLine();
        $line2->setDescription('Produit B');
        $line2->setQuantity(3);
        $line2->setUnitPrice('50.00');
        $line2->setTvaRate('10.00');
        $line2->recalculateTotalHt();
        $quote->addLine($line2);

        // Recalculer les totaux
        $quote->recalculateTotalsFromLines();

        // Vérifier les montants
        $this->assertEquals('350.00', $quote->getMontantHT(), 'Le montant HT devrait être 350.00€');
        $this->assertEquals('55.00', $quote->getMontantTVA(), 'La TVA devrait être 55.00€ (40 + 15)');
        $this->assertEquals('405.00', $quote->getMontantTTC(), 'Le montant TTC devrait être 405.00€ (350 + 55)');
    }

    /**
     * Test : Calcul du détail des taux de TVA
     */
    public function testGetTvaRatesDetail(): void
    {
        $quote = new Quote();
        $quote->setTauxTVA('20.00');
        $quote->setUsePerLineTva(true);

        // Créer des lignes avec différents taux de TVA
        $line1 = new QuoteLine();
        $line1->setDescription('Produit A');
        $line1->setQuantity(1);
        $line1->setUnitPrice('100.00');
        $line1->setTvaRate('20.00');
        $line1->recalculateTotalHt();
        $quote->addLine($line1);

        $line2 = new QuoteLine();
        $line2->setDescription('Produit B');
        $line2->setQuantity(1);
        $line2->setUnitPrice('100.00');
        $line2->setTvaRate('10.00');
        $line2->recalculateTotalHt();
        $quote->addLine($line2);

        $line3 = new QuoteLine();
        $line3->setDescription('Produit C');
        $line3->setQuantity(1);
        $line3->setUnitPrice('100.00');
        $line3->setTvaRate('20.00');
        $line3->recalculateTotalHt();
        $quote->addLine($line3);

        // Récupérer le détail
        $detail = $quote->getTvaRatesDetail();

        // Vérifier que les taux sont correctement groupés
        $this->assertArrayHasKey('20.00', $detail, 'Le taux 20% devrait être présent');
        $this->assertArrayHasKey('10.00', $detail, 'Le taux 10% devrait être présent');

        // Vérifier les montants HT
        $this->assertEquals(200.0, $detail['20.00']['ht'], 'Le montant HT pour 20% devrait être 200€');
        $this->assertEquals(100.0, $detail['10.00']['ht'], 'Le montant HT pour 10% devrait être 100€');

        // Vérifier les montants TVA
        $this->assertEquals(40.0, $detail['20.00']['tva'], 'La TVA pour 20% devrait être 40€');
        $this->assertEquals(10.0, $detail['10.00']['tva'], 'La TVA pour 10% devrait être 10€');
    }

    /**
     * Test : Calcul sans TVA (micro-entreprise)
     */
    public function testCalculateWithoutTva(): void
    {
        $quote = new Quote();
        $quote->setTauxTVA('0.00');
        $quote->setUsePerLineTva(false);

        // Créer une ligne : 100€ × 2 = 200€
        $line1 = new QuoteLine();
        $line1->setDescription('Produit A');
        $line1->setQuantity(2);
        $line1->setUnitPrice('100.00');
        $line1->recalculateTotalHt();
        $quote->addLine($line1);

        // Recalculer les totaux
        $quote->recalculateTotalsFromLines();

        // Vérifier les montants
        $this->assertEquals('200.00', $quote->getMontantHT(), 'Le montant HT devrait être 200.00€');
        $this->assertEquals('0.00', $quote->getMontantTVA(), 'La TVA devrait être 0.00€');
        $this->assertEquals('200.00', $quote->getMontantTTC(), 'Le montant TTC devrait être 200.00€ (égal au HT)');
    }
}

