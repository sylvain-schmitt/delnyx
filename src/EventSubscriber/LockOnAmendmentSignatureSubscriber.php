<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Amendment;
use App\Entity\AmendmentStatus;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * EventSubscriber pour verrouiller les avenants après signature
 * 
 * Conformité légale française : un avenant signé ne peut plus être modifié.
 */
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate')]
class LockOnAmendmentSignatureSubscriber
{
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Amendment) {
            $this->handleAmendmentSignature($entity, $args);
        }
    }

    private function handleAmendmentSignature(Amendment $amendment, LifecycleEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();
        $changeset = $uow->getEntityChangeSet($amendment);

        // Vérifier si le statut a changé vers SIGNED ou si la signature a été ajoutée
        $statusChanged = isset($changeset['statut']);
        $signatureAdded = isset($changeset['signatureClient']) && 
                         $amendment->getSignatureClient() !== null && 
                         ($changeset['signatureClient'][0] === null || $changeset['signatureClient'][0] === '');

        if ($statusChanged && $amendment->getStatut() === AmendmentStatus::SIGNED) {
            // Valider avant signature
            $amendment->validateCanBeSigned();
            
            // Le statut a été changé vers SIGNED
            if (!$amendment->getDateSignature()) {
                $amendment->setDateSignature(new \DateTime());
            }
        } elseif ($signatureAdded && $amendment->getSignatureClient() !== null) {
            // Valider avant signature
            $amendment->validateCanBeSigned();
            
            // La signature a été ajoutée, passer le statut à SIGNED
            $amendment->setStatut(AmendmentStatus::SIGNED);
            if (!$amendment->getDateSignature()) {
                $amendment->setDateSignature(new \DateTime());
            }
        }

        // Vérifier si l'avenant est signé et empêcher les modifications
        if ($amendment->getStatut() === AmendmentStatus::SIGNED) {
            $this->preventModifications($amendment, $changeset);
        }
    }

    /**
     * Empêche les modifications d'un avenant signé
     * 
     * @throws \RuntimeException si une tentative de modification est détectée
     */
    private function preventModifications(Amendment $amendment, array $changeset): void
    {
        // Champs autorisés à être modifiés même après signature
        $allowedFields = [
            'statut',           // Pour passer à CANCELLED
            'dateSignature',    // Pour enregistrer la signature
            'signatureClient',  // Pour enregistrer la signature
            'dateModification', // Horodatage automatique
            'pdfFilename',      // Nom du fichier PDF généré (technique, ne change pas le contenu)
            'pdfHash'           // Hash du PDF généré (technique, ne change pas le contenu)
        ];

        foreach ($changeset as $field => $change) {
            if (!in_array($field, $allowedFields)) {
                throw new \RuntimeException(
                    sprintf(
                        'L\'avenant #%s est signé et ne peut plus être modifié. Le champ "%s" ne peut pas être changé.',
                        $amendment->getNumero() ?? $amendment->getId(),
                        $field
                    )
                );
            }
        }
    }
}

