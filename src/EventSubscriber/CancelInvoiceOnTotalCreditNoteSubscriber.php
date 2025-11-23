<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\CreditNote;
use App\Entity\CreditNoteStatus;
use App\Entity\InvoiceStatus;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * EventSubscriber pour annuler automatiquement une facture lorsqu'un avoir total est émis
 * 
 * Règle métier : Si le total des avoirs émis = montant TTC de la facture, la facture est automatiquement annulée
 * Conformité légale française : Un avoir total annule la facture d'origine
 */
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate')]
#[AsEntityListener(event: Events::postPersist, method: 'postPersist')]
class CancelInvoiceOnTotalCreditNoteSubscriber
{
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof CreditNote) {
            $this->handleCreditNoteEmission($entity, $args);
        }
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof CreditNote) {
            $this->handleCreditNoteEmission($entity, $args);
        }
    }

    private function handleCreditNoteEmission(CreditNote $creditNote, LifecycleEventArgs $args): void
    {
        // Vérifier si l'avoir est émis (ISSUED) - vérifier explicitement le statut
        $statutEnum = $creditNote->getStatutEnum();
        if (!$statutEnum || $statutEnum !== CreditNoteStatus::ISSUED) {
            return; // Avoir non émis, rien à faire
        }

        $invoice = $creditNote->getInvoice();
        if (!$invoice) {
            return; // Pas de facture associée
        }

        // Vérifier que la facture n'est pas déjà annulée
        $invoiceStatut = $invoice->getStatutEnum();
        if (!$invoiceStatut || $invoiceStatut === InvoiceStatus::CANCELLED) {
            return; // Facture déjà annulée
        }

        // Calculer le total de tous les avoirs émis pour cette facture
        $em = $args->getObjectManager();
        
        // Recharger la facture depuis la base pour avoir les avoirs à jour
        $em->refresh($invoice);
        
        $totalAvoirsEmitted = 0.0;
        
        foreach ($invoice->getCreditNotes() as $existingCreditNote) {
            $existingStatut = $existingCreditNote->getStatutEnum();
            // Vérifier explicitement que le statut est ISSUED
            if ($existingStatut && $existingStatut === CreditNoteStatus::ISSUED) {
                // Les avoirs sont stockés en montants négatifs, donc on additionne directement
                $totalAvoirsEmitted += (float) $existingCreditNote->getMontantTTC();
            }
        }

        $montantFactureTTC = (float) $invoice->getMontantTTC();

        // Si les avoirs sont négatifs, vérifier si le solde final est 0 (avoir total)
        // Exemple : facture 200€ + avoir -200€ = 0€ (avoir total)
        // Avec tolérance de 0.01 € pour arrondis
        $soldeFinal = $montantFactureTTC + $totalAvoirsEmitted;
        if (abs($soldeFinal) < 0.01) {
            // C'est un avoir total : annuler la facture
            $invoice->setStatut(InvoiceStatus::CANCELLED->value);
            $invoice->setDateModification(new \DateTime());
            
            // Forcer la mise à jour de la facture
            $em->persist($invoice);
            $em->flush();
            
            // Note: Le message flash sera géré par le contrôleur qui émet l'avoir
            // car l'EventSubscriber ne peut pas accéder directement à la session
        }
    }
}

