<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\CreditNote;
use App\Entity\CreditNoteStatus;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * EventSubscriber pour verrouiller les avoirs après émission
 * 
 * Conformité légale française : un avoir émis (ISSUED) ne peut plus être modifié.
 */
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate')]
class LockOnCreditNoteEmissionSubscriber
{
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof CreditNote) {
            $this->handleCreditNoteEmission($entity, $args);
        }
    }

    private function handleCreditNoteEmission(CreditNote $creditNote, LifecycleEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();
        $changeset = $uow->getEntityChangeSet($creditNote);

        $statutEnum = $creditNote->getStatutEnum();
        
        // Vérifier si l'avoir est émis (ne peut plus être modifié)
        if ($statutEnum && $statutEnum->isEmitted()) {
            $this->preventModifications($creditNote, $changeset);
        }
    }

    /**
     * Empêche les modifications d'un avoir émis
     * 
     * @throws \RuntimeException si une tentative de modification est détectée
     */
    private function preventModifications(CreditNote $creditNote, array $changeset): void
    {
        // Champs autorisés à être modifiés même après émission
        $allowedFields = [
            'statut',           // Pour passer à un autre statut (si besoin)
            'dateEmission',     // Pour enregistrer l'émission
            'dateModification'  // Horodatage automatique
        ];

        foreach ($changeset as $field => $change) {
            if (!in_array($field, $allowedFields)) {
                throw new \RuntimeException(
                    sprintf(
                        'L\'avoir #%s est émis et ne peut plus être modifié. Le champ "%s" ne peut pas être changé.',
                        $creditNote->getNumber() ?? $creditNote->getId(),
                        $field
                    )
                );
            }
        }
    }
}

