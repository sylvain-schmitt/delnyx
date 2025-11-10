<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Quote;
use App\Entity\Invoice;
use App\Entity\Amendment;
use App\Entity\CreditNote;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * EventSubscriber pour la numérotation automatique des documents
 * 
 * Formats :
 * - Quote : DEV-YYYY-MM-XXX (ex: DEV-2025-01-001)
 * - Invoice : Numérotation séquentielle sans rupture (ex: FACT-2025-001)
 * - Amendment : Dérivée du devis : YYYY-XXX-A1 (ex: 2025-001-A1)
 * - CreditNote : AV-YYYY-### (ex: AV-2025-001)
 */
#[AsEntityListener(event: Events::prePersist, method: 'prePersist')]
class AutoNumberingSubscriber
{
    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

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
     * Génère le numéro de devis : DEV-YYYY-MM-XXX
     */
    private function generateQuoteNumber(Quote $quote, LifecycleEventArgs $args): void
    {
        if ($quote->getNumero() !== null) {
            return; // Numéro déjà défini
        }

        $em = $args->getObjectManager();
        $year = (int) date('Y');
        $month = date('m');

        // Trouver le dernier numéro pour ce mois
        $lastQuote = $em->getRepository(Quote::class)->createQueryBuilder('q')
            ->where('q.numero LIKE :pattern')
            ->setParameter('pattern', sprintf('DEV-%d-%s-%%', $year, $month))
            ->orderBy('q.numero', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $sequence = 1;
        if ($lastQuote && $lastQuote->getNumero()) {
            // Extraire le numéro de séquence du dernier devis
            $parts = explode('-', $lastQuote->getNumero());
            if (count($parts) === 4) {
                $sequence = (int) $parts[3] + 1;
            }
        }

        $quote->setNumero(sprintf('DEV-%d-%s-%03d', $year, $month, $sequence));
    }

    /**
     * Génère le numéro de facture : FACT-YYYY-XXX (séquentiel sans rupture)
     */
    private function generateInvoiceNumber(Invoice $invoice, LifecycleEventArgs $args): void
    {
        if ($invoice->getNumero() !== null) {
            return; // Numéro déjà défini
        }

        $em = $args->getObjectManager();
        $year = (int) date('Y');

        // Trouver le dernier numéro pour cette année
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
            $parts = explode('-', $lastInvoice->getNumero());
            if (count($parts) === 3) {
                $sequence = (int) $parts[2] + 1;
            }
        }

        $invoice->setNumero(sprintf('FACT-%d-%03d', $year, $sequence));
    }

    /**
     * Génère le numéro d'avenant : YYYY-XXX-A1 (dérivé du devis)
     */
    private function generateAmendmentNumber(Amendment $amendment, LifecycleEventArgs $args): void
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
        // Format devis : DEV-YYYY-MM-XXX
        $quoteParts = explode('-', $quote->getNumero());
        if (count($quoteParts) !== 4) {
            return; // Format de devis invalide
        }

        $year = $quoteParts[1];
        $quoteSequence = $quoteParts[3];

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
    private function generateCreditNoteNumber(CreditNote $creditNote, LifecycleEventArgs $args): void
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

