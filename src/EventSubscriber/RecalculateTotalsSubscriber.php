<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\CreditNote;
use App\Entity\CreditNoteLine;
use App\Entity\Amendment;
use App\Entity\AmendmentLine;
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
        } elseif ($entity instanceof AmendmentLine) {
            $this->recalculateAmendmentTotals($entity, $args);
        } elseif ($entity instanceof Quote) {
            $this->recalculateQuoteFromLines($entity);
        } elseif ($entity instanceof Invoice) {
            $this->recalculateInvoiceFromLines($entity);
        } elseif ($entity instanceof CreditNote) {
            $this->recalculateCreditNoteFromLines($entity);
        } elseif ($entity instanceof Amendment) {
            $this->recalculateAmendmentFromLines($entity);
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
     * Recalcule les totaux d'un avenant à partir de ses lignes
     */
    private function recalculateAmendmentTotals(AmendmentLine $line, LifecycleEventArgs $args): void
    {
        $amendment = $line->getAmendment();
        if (!$amendment) {
            return;
        }

        $this->recalculateAmendmentFromLines($amendment);
    }

    /**
     * Recalcule les totaux d'un devis depuis toutes ses lignes
     * Les montants sont stockés en DECIMAL (euros)
     */
    private function recalculateQuoteFromLines(Quote $quote): void
    {
        $quote->recalculateTotalsFromLines();
    }

    /**
     * Recalcule les totaux d'une facture depuis toutes ses lignes
     * Les montants sont stockés en DECIMAL (euros)
     */
    private function recalculateInvoiceFromLines(Invoice $invoice): void
    {
        $invoice->recalculateTotalsFromLines();
    }

    /**
     * Recalcule les totaux d'un avoir depuis toutes ses lignes
     * Les montants sont stockés en DECIMAL (euros)
     */
    private function recalculateCreditNoteFromLines(CreditNote $creditNote): void
    {
        $creditNote->recalculateTotals();
    }

    /**
     * Recalcule les totaux d'un avenant depuis toutes ses lignes
     * Les montants sont stockés en DECIMAL (euros)
     */
    private function recalculateAmendmentFromLines(Amendment $amendment): void
    {
        $amendment->recalculateTotalsFromLines();
    }
}

