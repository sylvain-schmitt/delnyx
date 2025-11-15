<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Quote;
use App\Entity\Invoice;
use App\Entity\Amendment;
use App\Entity\CreditNote;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

/**
 * EventSubscriber pour la numérotation automatique des documents
 * 
 * Formats :
 * - Quote : DEV-YYYY-XXX (ex: DEV-2025-001) - Séquentiel par année
 * - Invoice : FACT-YYYY-XXX (ex: FACT-2025-001) - Séquentiel par année
 * - Amendment : YYYY-XXX-A1 (ex: 2025-001-A1) - Dérivé du devis
 * - CreditNote : AV-YYYY-XXX (ex: AV-2025-001) - Séquentiel par année
 */
#[AsEntityListener(event: Events::prePersist, entity: Quote::class)]
#[AsEntityListener(event: Events::prePersist, entity: Invoice::class)]
#[AsEntityListener(event: Events::prePersist, entity: Amendment::class)]
#[AsEntityListener(event: Events::prePersist, entity: CreditNote::class)]
class AutoNumberingSubscriber
{
    public function prePersist(Quote|Invoice|Amendment|CreditNote $entity, PrePersistEventArgs $args): void
    {
        if ($entity instanceof Quote) {
            $this->generateQuoteNumber($entity, $args);
        } elseif ($entity instanceof Invoice) {
            $this->generateInvoiceNumber($entity, $args);
        } elseif ($entity instanceof Amendment) {
            $this->generateAmendmentNumber($entity, $args);
        } elseif ($entity instanceof CreditNote) {
            $this->generateCreditNoteNumber($entity, $args);
        }
    }

    /**
     * Génère le numéro de devis : DEV-YYYY-XXX (séquentiel par année)
     * 
     * Conformité légale française : la numérotation doit être séquentielle et continue,
     * même pour les devis annulés (ils conservent leur numéro et font partie de la séquence).
     */
    private function generateQuoteNumber(Quote $quote, PrePersistEventArgs $args): void
    {
        if ($quote->getNumero() !== null) {
            return; // Numéro déjà défini
        }

        $em = $args->getObjectManager();
        $year = (int) date('Y');

        // Trouver le dernier numéro pour cette année
        // IMPORTANT : on inclut TOUS les devis (y compris annulés) pour respecter la séquence légale
        // Support des deux formats : ancien DEV-YYYY-MM-XXX et nouveau DEV-YYYY-XXX
        $lastQuote = $em->getRepository(Quote::class)->createQueryBuilder('q')
            ->where('q.numero LIKE :pattern')
            ->setParameter('pattern', sprintf('DEV-%d-%%', $year))
            ->orderBy('q.numero', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $sequence = 1;
        if ($lastQuote && $lastQuote->getNumero()) {
            // Extraire le numéro de séquence du dernier devis
            // Support des deux formats :
            // - Ancien : DEV-YYYY-MM-XXX (4 parties)
            // - Nouveau : DEV-YYYY-XXX (3 parties)
            $parts = explode('-', $lastQuote->getNumero());
            if (count($parts) === 4) {
                // Ancien format : DEV-YYYY-MM-XXX
                $sequence = (int) $parts[3] + 1;
            } elseif (count($parts) === 3 && is_numeric($parts[2])) {
                // Nouveau format : DEV-YYYY-XXX
                $sequence = (int) $parts[2] + 1;
            }
        }

        // Générer le numéro au format DEV-YYYY-XXX (ex: DEV-2025-001)
        $quote->setNumero(sprintf('DEV-%d-%03d', $year, $sequence));
    }

    /**
     * Génère le numéro de facture : FACT-YYYY-XXX (séquentiel sans rupture)
     * 
     * Conformité légale française : la numérotation doit être séquentielle et continue,
     * même pour les factures annulées (elles conservent leur numéro et font partie de la séquence).
     */
    private function generateInvoiceNumber(Invoice $invoice, PrePersistEventArgs $args): void
    {
        if ($invoice->getNumero() !== null) {
            return; // Numéro déjà défini
        }

        $em = $args->getObjectManager();
        $year = (int) date('Y');

        // Trouver le dernier numéro pour cette année
        // IMPORTANT : on inclut TOUTES les factures (y compris annulées) pour respecter la séquence légale
        $lastInvoice = $em->getRepository(Invoice::class)->createQueryBuilder('i')
            ->where('i.numero LIKE :pattern')
            ->setParameter('pattern', sprintf('FACT-%d-%%', $year))
            ->orderBy('i.numero', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $sequence = 1;
        if ($lastInvoice && $lastInvoice->getNumero()) {
            // Extraire le numéro de séquence de la dernière facture
            // Format attendu : FACT-YYYY-XXX
            $parts = explode('-', $lastInvoice->getNumero());
            if (count($parts) === 3 && is_numeric($parts[2])) {
                $sequence = (int) $parts[2] + 1;
            }
        }

        // Générer le numéro au format FACT-YYYY-XXX (ex: FACT-2025-001)
        $invoice->setNumero(sprintf('FACT-%d-%03d', $year, $sequence));
    }

    /**
     * Génère le numéro d'avenant : DEV-YYYY-XXX-A1 (dérivé du devis)
     * Format conforme : DEVISNUMBER-A#
     */
    private function generateAmendmentNumber(Amendment $amendment, PrePersistEventArgs $args): void
    {
        if ($amendment->getNumero() !== null) {
            return; // Numéro déjà défini
        }

        $quote = $amendment->getQuote();
        if (!$quote || !$quote->getNumero()) {
            return; // Pas de devis associé ou devis sans numéro
        }

        $em = $args->getObjectManager();

        // Utiliser le numéro complet du devis comme base
        // Format attendu : DEV-YYYY-XXX-A1, DEV-YYYY-XXX-A2, etc.
        $quoteNumber = $quote->getNumero();

        // Trouver le dernier avenant pour ce devis
        $lastAmendment = $em->getRepository(Amendment::class)->createQueryBuilder('a')
            ->where('a.quote = :quote')
            ->setParameter('quote', $quote)
            ->orderBy('a.numero', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $amendmentSequence = 1;
        if ($lastAmendment && $lastAmendment->getNumero()) {
            // Extraire le numéro de séquence de l'avenant
            // Format attendu : DEV-YYYY-XXX-A1, DEV-YYYY-XXX-A2, etc.
            $lastNumber = $lastAmendment->getNumero();
            // Chercher le pattern -A suivi d'un nombre à la fin
            if (preg_match('/-A(\d+)$/', $lastNumber, $matches)) {
                $amendmentSequence = (int) $matches[1] + 1;
            }
        }

        // Générer le numéro au format : DEV-YYYY-XXX-A1
        $amendment->setNumero(sprintf('%s-A%d', $quoteNumber, $amendmentSequence));
    }

    /**
     * Génère le numéro d'avoir : FA-XXX-C# (lié à la facture)
     * Format conforme : FACTURENUMBER-C# (ex: FACT-2025-001-C1, FACT-2025-001-C2)
     */
    private function generateCreditNoteNumber(CreditNote $creditNote, PrePersistEventArgs $args): void
    {
        if ($creditNote->getNumber() !== null) {
            return; // Numéro déjà défini
        }

        $invoice = $creditNote->getInvoice();
        if (!$invoice || !$invoice->getNumero()) {
            // Fallback : numérotation séquentielle par année si pas de facture
            $em = $args->getObjectManager();
            $year = (int) date('Y');

            $lastCreditNote = $em->getRepository(CreditNote::class)->createQueryBuilder('cn')
                ->where('cn.number LIKE :pattern')
                ->setParameter('pattern', sprintf('AV-%d-%%', $year))
                ->orderBy('cn.number', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            $sequence = 1;
            if ($lastCreditNote && $lastCreditNote->getNumber()) {
                $parts = explode('-', $lastCreditNote->getNumber());
                if (count($parts) === 3) {
                    $sequence = (int) $parts[2] + 1;
                }
            }

            $creditNote->setNumber(sprintf('AV-%d-%03d', $year, $sequence));
            return;
        }

        $em = $args->getObjectManager();
        $invoiceNumber = $invoice->getNumero();

        // Trouver le dernier avoir pour cette facture
        $lastCreditNote = $em->getRepository(CreditNote::class)->createQueryBuilder('cn')
            ->where('cn.invoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->orderBy('cn.number', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $creditNoteSequence = 1;
        if ($lastCreditNote && $lastCreditNote->getNumber()) {
            // Extraire le numéro de séquence de l'avoir
            // Format attendu : FACT-YYYY-XXX-C1, FACT-YYYY-XXX-C2, etc.
            $lastNumber = $lastCreditNote->getNumber();
            // Chercher le pattern -C suivi d'un nombre à la fin
            if (preg_match('/-C(\d+)$/', $lastNumber, $matches)) {
                $creditNoteSequence = (int) $matches[1] + 1;
            }
        }

        // Générer le numéro au format : FACT-YYYY-XXX-C1
        $creditNote->setNumber(sprintf('%s-C%d', $invoiceNumber, $creditNoteSequence));
    }
}

