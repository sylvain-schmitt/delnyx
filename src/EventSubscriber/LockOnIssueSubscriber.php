<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Invoice;
use App\Entity\InvoiceStatus;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * EventSubscriber pour verrouiller les factures émises
 * 
 * Empêche toute modification d'une facture une fois qu'elle est émise (ISSUED, PAID, CANCELLED)
 * Conformité légale française : une facture émise est immuable
 * 
 * @package App\EventSubscriber
 */
#[AsDoctrineListener(event: Events::preUpdate, priority: 500)]
class LockOnIssueSubscriber
{
    /**
     * Empêche la modification d'une facture émise
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Invoice) {
            return;
        }

        // Récupérer l'ancien statut (avant modification)
        $oldStatusValue = $args->hasChangedField('statut') ? $args->getOldValue('statut') : $entity->getStatut();
        $oldStatusEnum = $oldStatusValue ? InvoiceStatus::from($oldStatusValue) : null;

        // Vérifier si on essaie de modifier le statut
        if ($args->hasChangedField('statut')) {
            $oldStatus = $args->getOldValue('statut');
            $newStatus = $args->getNewValue('statut');

            // Autoriser la transition DRAFT → ISSUED (émission)
            if ($oldStatus === InvoiceStatus::DRAFT->value && $newStatus === InvoiceStatus::ISSUED->value) {
                // Transition autorisée, on continue pour vérifier les autres champs
            } elseif ($oldStatusEnum && $oldStatusEnum->isEmitted()) {
                // Pour les factures déjà émises, autoriser uniquement les transitions légales :
                // - ISSUED → SENT (via send)
                // - ISSUED → PAID (via markPaid)
                // - ISSUED → CANCELLED (via avoir total)
                // - SENT → PAID (via markPaid)
                // - PAID → CANCELLED (via avoir total)
                $allowedTransitions = [
                    InvoiceStatus::ISSUED->value => [InvoiceStatus::SENT->value, InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value],
                    InvoiceStatus::SENT->value => [InvoiceStatus::PAID->value],
                    InvoiceStatus::PAID->value => [InvoiceStatus::CANCELLED->value],
                ];

                if (!isset($allowedTransitions[$oldStatus]) || !in_array($newStatus, $allowedTransitions[$oldStatus])) {
                    throw new AccessDeniedHttpException(
                        sprintf(
                            'La facture #%s est émise et ne peut plus être modifiée. Transition de "%s" vers "%s" non autorisée.',
                            $entity->getNumero() ?? 'N/A',
                            $oldStatus,
                            $newStatus
                        )
                    );
                }
            }
        }

        // Si la facture était déjà émise AVANT cette modification, empêcher la modification des champs sensibles
        if ($oldStatusEnum && $oldStatusEnum->isEmitted()) {
            // Empêcher la modification des autres champs (sauf ceux autorisés)
            // Champs autorisés pour les factures émises :
            // - statut (géré ci-dessus)
            // - datePaiement (lors du marquage comme payée)
            // - dateEnvoi (lors de l'envoi - peut être fait plusieurs fois)
            // - sentCount (incrémenté lors de l'envoi)
            // - deliveryChannel (mis à jour lors de l'envoi)
            // - dateModification (mis à jour automatiquement)
            // - pdfFilename (nom du fichier PDF généré, technique)
            // - pdfHash (hash du PDF généré, technique)
            $allowedFields = ['statut', 'datePaiement', 'dateEnvoi', 'sentCount', 'deliveryChannel', 'dateModification', 'pdfFilename', 'pdfHash'];
            $changedFields = array_keys($args->getEntityChangeSet());

            foreach ($changedFields as $field) {
                if (!in_array($field, $allowedFields)) {
                    throw new AccessDeniedHttpException(
                        sprintf(
                            'La facture #%s est émise et ne peut plus être modifiée. Le champ "%s" ne peut pas être modifié.',
                            $entity->getNumero() ?? 'N/A',
                            $field
                        )
                    );
                }
            }
        }
    }
}

