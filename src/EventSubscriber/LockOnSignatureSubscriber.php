<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Quote;
use App\Entity\QuoteStatus;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * EventSubscriber pour verrouiller les documents après signature
 * 
 * Lorsqu'un devis est signé :
 * - Le statut passe à SIGNED
 * - Le document devient immuable (ne peut plus être modifié)
 * - La date de signature est enregistrée
 */
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate')]
class LockOnSignatureSubscriber
{
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Quote) {
            $this->handleQuoteSignature($entity, $args);
        }
    }

    /**
     * Gère la signature d'un devis
     */
    private function handleQuoteSignature(Quote $quote, LifecycleEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();
        $changeset = $uow->getEntityChangeSet($quote);

        // Vérifier si le statut a changé vers SIGNED ou si la signature a été ajoutée
        $statusChanged = isset($changeset['statut']);
        $signatureAdded = isset($changeset['signatureClient']) && 
                         $quote->getSignatureClient() !== null && 
                         ($changeset['signatureClient'][0] === null || $changeset['signatureClient'][0] === '');

        if ($statusChanged && $quote->getStatut() === QuoteStatus::SIGNED) {
            // Valider avant signature
            $quote->validateCanBeSigned();
            
            // Le statut a été changé vers SIGNED
            if (!$quote->getDateSignature()) {
                $quote->setDateSignature(new \DateTime());
            }
        } elseif ($signatureAdded && $quote->getSignatureClient() !== null) {
            // Valider avant signature
            $quote->validateCanBeSigned();
            
            // La signature a été ajoutée, passer le statut à SIGNED
            $quote->setStatut(QuoteStatus::SIGNED);
            if (!$quote->getDateSignature()) {
                $quote->setDateSignature(new \DateTime());
            }
        }

        // Vérifier si le devis est signé et empêcher les modifications
        if ($quote->getStatut() === QuoteStatus::SIGNED) {
            $this->preventModifications($quote, $changeset);
        }
    }

    /**
     * Empêche les modifications d'un devis signé
     * 
     * @throws \RuntimeException si une tentative de modification est détectée
     */
    private function preventModifications(Quote $quote, array $changeset): void
    {
        // Champs autorisés à être modifiés même après signature
        $allowedFields = ['statut', 'dateSignature', 'signatureClient', 'dateModification'];

        foreach ($changeset as $field => $change) {
            if (!in_array($field, $allowedFields)) {
                throw new \RuntimeException(
                    sprintf(
                        'Le devis #%s est signé et ne peut plus être modifié. Le champ "%s" ne peut pas être changé.',
                        $quote->getNumero() ?? $quote->getId(),
                        $field
                    )
                );
            }
        }
    }
}

