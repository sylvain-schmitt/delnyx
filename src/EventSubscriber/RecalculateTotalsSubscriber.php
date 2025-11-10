<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\CreditNote;
use App\Entity\CreditNoteLine;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * EventSubscriber pour recalculer automatiquement les totaux des documents
 * lorsque les lignes sont modifiées
 */
#[AsEntityListener(event: Events::prePersist, method: 'prePersist')]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate')]
class RecalculateTotalsSubscriber
{
    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->recalculateTotals($args);
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->recalculateTotals($args);
    }

    private function recalculateTotals(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof QuoteLine) {
            $this->recalculateQuoteTotals($entity, $args);
        } elseif ($entity instanceof InvoiceLine) {
            $this->recalculateInvoiceTotals($entity, $args);
        } elseif ($entity instanceof CreditNoteLine) {
            $this->recalculateCreditNoteTotals($entity, $args);
        } elseif ($entity instanceof Quote) {
            $this->recalculateQuoteFromLines($entity);
        } elseif ($entity instanceof Invoice) {
            $this->recalculateInvoiceFromLines($entity);
        } elseif ($entity instanceof CreditNote) {
            $this->recalculateCreditNoteFromLines($entity);
        }
    }

    /**
     * Recalcule les totaux d'un devis à partir de ses lignes
     */
    private function recalculateQuoteTotals(QuoteLine $line, LifecycleEventArgs $args): void
    {
        $quote = $line->getQuote();
        if (!$quote) {
            return;
        }

        $this->recalculateQuoteFromLines($quote);
    }

    /**
     * Recalcule les totaux d'une facture à partir de ses lignes
     */
    private function recalculateInvoiceTotals(InvoiceLine $line, LifecycleEventArgs $args): void
    {
        $invoice = $line->getInvoice();
        if (!$invoice) {
            return;
        }

        $this->recalculateInvoiceFromLines($invoice);
    }

    /**
     * Recalcule les totaux d'un avoir à partir de ses lignes
     */
    private function recalculateCreditNoteTotals(CreditNoteLine $line, LifecycleEventArgs $args): void
    {
        $creditNote = $line->getCreditNote();
        if (!$creditNote) {
            return;
        }

        $this->recalculateCreditNoteFromLines($creditNote);
    }

    /**
     * Recalcule les totaux d'un devis depuis toutes ses lignes
     */
    private function recalculateQuoteFromLines(Quote $quote): void
    {
        $totalHT = 0;
        $totalTVA = 0;

        foreach ($quote->getLines() as $line) {
            $lineTotalHT = $line->getTotalHt() ?? 0;
            $totalHT += $lineTotalHT;

            // Calculer la TVA pour cette ligne
            if ($line->getTvaRate() && (float) $line->getTvaRate() > 0) {
                $tvaAmount = (int) round($lineTotalHT * ((float) $line->getTvaRate() / 100));
                $totalTVA += $tvaAmount;
            } elseif ($quote->getTauxTVA() && (float) $quote->getTauxTVA() > 0) {
                // Utiliser le taux de TVA du devis si la ligne n'en a pas
                $tvaAmount = (int) round($lineTotalHT * ((float) $quote->getTauxTVA() / 100));
                $totalTVA += $tvaAmount;
            }
        }

        $quote->setMontantHT((string) ($totalHT / 100)); // Conversion centimes -> euros (string)
        $quote->setMontantTVA((string) ($totalTVA / 100));
        $quote->setMontantTTC((string) (($totalHT + $totalTVA) / 100));
    }

    /**
     * Recalcule les totaux d'une facture depuis toutes ses lignes
     */
    private function recalculateInvoiceFromLines(Invoice $invoice): void
    {
        $totalHT = 0;
        $totalTVA = 0;

        foreach ($invoice->getLines() as $line) {
            $lineTotalHT = $line->getTotalHt() ?? 0;
            $totalHT += $lineTotalHT;

            // Calculer la TVA pour cette ligne
            if ($line->getTvaRate() && (float) $line->getTvaRate() > 0) {
                $tvaAmount = (int) round($lineTotalHT * ((float) $line->getTvaRate() / 100));
                $totalTVA += $tvaAmount;
            } elseif ($invoice->getQuote() && $invoice->getQuote()->getTauxTVA() && (float) $invoice->getQuote()->getTauxTVA() > 0) {
                // Utiliser le taux de TVA du devis si la ligne n'en a pas
                $tvaAmount = (int) round($lineTotalHT * ((float) $invoice->getQuote()->getTauxTVA() / 100));
                $totalTVA += $tvaAmount;
            }
        }

        $invoice->setMontantHT((string) ($totalHT / 100)); // Conversion centimes -> euros (string)
        $invoice->setMontantTVA((string) ($totalTVA / 100));
        $invoice->setMontantTTC((string) (($totalHT + $totalTVA) / 100));
    }

    /**
     * Recalcule les totaux d'un avoir depuis toutes ses lignes
     */
    private function recalculateCreditNoteFromLines(CreditNote $creditNote): void
    {
        $creditNote->recalculateTotals();
    }
}

