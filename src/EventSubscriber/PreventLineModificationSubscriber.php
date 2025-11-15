<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\AmendmentLine;
use App\Entity\CreditNoteLine;
use App\Entity\AmendmentStatus;
use App\Entity\CreditNoteStatus;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * EventSubscriber pour empêcher la modification des lignes d'avenant/avoir signés
 * 
 * CONFORMITÉ LÉGALE :
 * - Un avenant signé est immuable, ses lignes aussi
 * - Un avoir émis est immuable, ses lignes aussi
 * - Aucune modification possible après signature/émission
 */
#[AsEntityListener(event: Events::preUpdate, entity: AmendmentLine::class)]
#[AsEntityListener(event: Events::preUpdate, entity: CreditNoteLine::class)]
#[AsEntityListener(event: Events::preRemove, entity: AmendmentLine::class)]
#[AsEntityListener(event: Events::preRemove, entity: CreditNoteLine::class)]
class PreventLineModificationSubscriber
{
    /**
     * Empêche la modification d'une ligne d'avenant si l'avenant est signé
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof AmendmentLine) {
            $this->preventAmendmentLineModification($entity);
        } elseif ($entity instanceof CreditNoteLine) {
            $this->preventCreditNoteLineModification($entity);
        }
    }

    /**
     * Empêche la suppression d'une ligne d'avenant/avoir si signé/émis
     */
    public function preRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof AmendmentLine) {
            $this->preventAmendmentLineDeletion($entity);
        } elseif ($entity instanceof CreditNoteLine) {
            $this->preventCreditNoteLineDeletion($entity);
        }
    }

    private function preventAmendmentLineModification(AmendmentLine $line): void
    {
        $amendment = $line->getAmendment();
        
        if (!$amendment) {
            return; // Pas d'avenant associé, pas de restriction
        }

        $statut = $amendment->getStatutEnum();
        
        if ($statut && $statut === AmendmentStatus::SIGNED) {
            throw new \RuntimeException(
                sprintf(
                    'Impossible de modifier une ligne d\'avenant signé. L\'avenant %s est signé et immuable.',
                    $amendment->getNumero() ?? '#' . $amendment->getId()
                )
            );
        }
    }

    private function preventCreditNoteLineModification(CreditNoteLine $line): void
    {
        $creditNote = $line->getCreditNote();
        
        if (!$creditNote) {
            return; // Pas d'avoir associé, pas de restriction
        }

        $statut = $creditNote->getStatutEnum();
        
        if ($statut && $statut === CreditNoteStatus::ISSUED) {
            throw new \RuntimeException(
                sprintf(
                    'Impossible de modifier une ligne d\'avoir émis. L\'avoir %s est émis et immuable.',
                    $creditNote->getNumber() ?? '#' . $creditNote->getId()
                )
            );
        }
    }

    private function preventAmendmentLineDeletion(AmendmentLine $line): void
    {
        $amendment = $line->getAmendment();
        
        if (!$amendment) {
            return;
        }

        $statut = $amendment->getStatutEnum();
        
        if ($statut && $statut === AmendmentStatus::SIGNED) {
            throw new \RuntimeException(
                sprintf(
                    'Impossible de supprimer une ligne d\'avenant signé. L\'avenant %s est signé et immuable.',
                    $amendment->getNumero() ?? '#' . $amendment->getId()
                )
            );
        }
    }

    private function preventCreditNoteLineDeletion(CreditNoteLine $line): void
    {
        $creditNote = $line->getCreditNote();
        
        if (!$creditNote) {
            return;
        }

        $statut = $creditNote->getStatutEnum();
        
        if ($statut && $statut === CreditNoteStatus::ISSUED) {
            throw new \RuntimeException(
                sprintf(
                    'Impossible de supprimer une ligne d\'avoir émis. L\'avoir %s est émis et immuable.',
                    $creditNote->getNumber() ?? '#' . $creditNote->getId()
                )
            );
        }
    }
}

