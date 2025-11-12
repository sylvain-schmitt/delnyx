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
     * Génère le numéro d'avenant : YYYY-XXX-A1 (dérivé du devis)
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

        // Extraire l'année et le numéro de séquence du devis
        // Support des deux formats :
        // - Ancien : DEV-YYYY-MM-XXX (4 parties)
        // - Nouveau : DEV-YYYY-XXX (3 parties)
        $quoteParts = explode('-', $quote->getNumero());
        if (count($quoteParts) === 4) {
            // Ancien format : DEV-YYYY-MM-XXX
            $year = $quoteParts[1];
            $quoteSequence = $quoteParts[3];
        } elseif (count($quoteParts) === 3 && is_numeric($quoteParts[2])) {
            // Nouveau format : DEV-YYYY-XXX
            $year = $quoteParts[1];
            $quoteSequence = $quoteParts[2];
        } else {
            return; // Format de devis invalide
        }

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
            // Extraire le numéro de séquence de l'avenant (format: YYYY-XXX-A1)
            $amendmentParts = explode('-', $lastAmendment->getNumero());
            if (count($amendmentParts) === 3) {
                $lastSequence = (int) str_replace('A', '', $amendmentParts[2]);
                $amendmentSequence = $lastSequence + 1;
            }
        }

        $amendment->setNumero(sprintf('%s-%s-A%d', $year, $quoteSequence, $amendmentSequence));
    }

    /**
     * Génère le numéro d'avoir : AV-YYYY-XXX
     */
    private function generateCreditNoteNumber(CreditNote $creditNote, PrePersistEventArgs $args): void
    {
        if ($creditNote->getNumber() !== null) {
            return; // Numéro déjà défini
        }

        $em = $args->getObjectManager();
        $year = (int) date('Y');

        // Trouver le dernier numéro pour cette année
        $lastCreditNote = $em->getRepository(CreditNote::class)->createQueryBuilder('cn')
            ->where('cn.number LIKE :pattern')
            ->setParameter('pattern', sprintf('AV-%d-%%', $year))
            ->orderBy('cn.number', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $sequence = 1;
        if ($lastCreditNote && $lastCreditNote->getNumber()) {
            // Extraire le numéro de séquence du dernier avoir
            $parts = explode('-', $lastCreditNote->getNumber());
            if (count($parts) === 3) {
                $sequence = (int) $parts[2] + 1;
            }
        }

        $creditNote->setNumber(sprintf('AV-%d-%03d', $year, $sequence));
    }
}

