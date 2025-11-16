<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Amendment;
use App\Entity\AmendmentStatus;
use App\Entity\CreditNote;
use App\Entity\CreditNoteStatus;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Subscriber Doctrine pour recalculer les totaux du document parent après chaque modification
 * 
 * CONFORMITÉ LÉGALE :
 * - Le total corrigé doit être recalculé UNIQUEMENT à chaque modification d'un avenant/avoir modifiable
 * - Les avenants modifiables : DRAFT, SENT (peuvent être modifiés)
 * - Les avenants immuables : SIGNED (ne peuvent plus être modifiés, recalcul inutile)
 * - Les avoirs modifiables : DRAFT uniquement (peuvent être modifiés)
 * - Les avoirs immuables : ISSUED, SENT (ne peuvent plus être modifiés, recalcul inutile)
 * - Les avenants/avoirs annulés (CANCELLED) ne sont jamais recalculés
 * 
 * Le total corrigé (calculé par getTotalCorrected()) inclut tous les avenants/avoirs non annulés,
 * mais le recalcul n'est déclenché que pour les statuts modifiables.
 * 
 * Ce subscriber déclenche le recalcul automatique après chaque création/modification d'un avenant/avoir modifiable
 */
#[AsEntityListener(event: Events::preUpdate, entity: Amendment::class)]
#[AsEntityListener(event: Events::preUpdate, entity: CreditNote::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Amendment::class)]
#[AsEntityListener(event: Events::postUpdate, entity: CreditNote::class)]
#[AsEntityListener(event: Events::postPersist, entity: Amendment::class)]
#[AsEntityListener(event: Events::postPersist, entity: CreditNote::class)]
class RecalculateParentTotalsSubscriber
{
    /**
     * Stocke si un recalcul est nécessaire (pour éviter les recalculs inutiles)
     */
    private array $needsRecalculation = [];

    /**
     * Détecte si un recalcul est nécessaire avant la mise à jour
     */
    public function preUpdate(Amendment|CreditNote $entity, PreUpdateEventArgs $args): void
    {
        $entityId = $entity->getId();
        $changeSet = $args->getEntityChangeSet();

        if ($entity instanceof Amendment) {
            $status = $entity->getStatutEnum();
            // Recalculer UNIQUEMENT si l'avenant est modifiable (DRAFT, SENT)
            // Ne pas recalculer si l'avenant est signé (SIGNED) car il est immuable
            // Ne pas recalculer si l'avenant est annulé (CANCELLED)
            if ($status && $status->isModifiable()) {
                // Vérifier s'il y a eu des modifications (statut, lignes, etc.)
                if (isset($changeSet['statut']) || isset($changeSet['lines']) || !empty($changeSet)) {
                    $this->needsRecalculation['amendment_' . $entityId] = true;
                }
            }
        } elseif ($entity instanceof CreditNote) {
            $status = $entity->getStatutEnum();
            // Recalculer UNIQUEMENT si l'avoir est modifiable (DRAFT)
            // Ne pas recalculer si l'avoir est émis (ISSUED) ou envoyé (SENT) car il est immuable
            // Ne pas recalculer si l'avoir est annulé (CANCELLED)
            if ($status && $status->isModifiable()) {
                // Vérifier s'il y a eu des modifications (statut, lignes, etc.)
                if (isset($changeSet['statut']) || isset($changeSet['lines']) || !empty($changeSet)) {
                    $this->needsRecalculation['credit_note_' . $entityId] = true;
                }
            }
        }
    }

    /**
     * Recalcule les totaux du document parent après création
     */
    public function postPersist(Amendment|CreditNote $entity, PostPersistEventArgs $args): void
    {
        $status = $entity->getStatutEnum();
        
        if ($entity instanceof Amendment) {
            // Recalculer UNIQUEMENT si l'avenant est modifiable (DRAFT, SENT)
            // Les avenants signés (SIGNED) sont immuables, pas besoin de recalculer
            if ($status && $status->isModifiable()) {
                $this->recalculateQuoteTotals($entity, $args);
            }
        } elseif ($entity instanceof CreditNote) {
            // Recalculer UNIQUEMENT si l'avoir est modifiable (DRAFT)
            // Les avoirs émis (ISSUED) ou envoyés (SENT) sont immuables, pas besoin de recalculer
            if ($status && $status->isModifiable()) {
                $this->recalculateInvoiceTotals($entity, $args);
            }
        }
    }

    /**
     * Recalcule les totaux du document parent après modification
     */
    public function postUpdate(Amendment|CreditNote $entity, PostUpdateEventArgs $args): void
    {
        $entityId = $entity->getId();

        if ($entity instanceof Amendment) {
            // Recalculer si nécessaire
            if (isset($this->needsRecalculation['amendment_' . $entityId])) {
                $this->recalculateQuoteTotals($entity, $args);
                unset($this->needsRecalculation['amendment_' . $entityId]);
            }
        } elseif ($entity instanceof CreditNote) {
            // Recalculer si nécessaire
            if (isset($this->needsRecalculation['credit_note_' . $entityId])) {
                $this->recalculateInvoiceTotals($entity, $args);
                unset($this->needsRecalculation['credit_note_' . $entityId]);
            }
        }
    }

    /**
     * Recalcule les totaux du devis parent
     * Le total corrigé est calculé dynamiquement par getTotalCorrected()
     * On force le refresh pour s'assurer que les relations sont à jour
     */
    private function recalculateQuoteTotals(Amendment $amendment, PostUpdateEventArgs|PostPersistEventArgs $args): void
    {
        $quote = $amendment->getQuote();
        if ($quote) {
            $em = $args->getObjectManager();
            $em->refresh($quote);
        }
    }

    /**
     * Recalcule les totaux de la facture parent
     * Le total corrigé est calculé dynamiquement par getTotalCorrected()
     * On force le refresh pour s'assurer que les relations sont à jour
     */
    private function recalculateInvoiceTotals(CreditNote $creditNote, PostUpdateEventArgs|PostPersistEventArgs $args): void
    {
        $invoice = $creditNote->getInvoice();
        if ($invoice) {
            $em = $args->getObjectManager();
            $em->refresh($invoice);
        }
    }
}

