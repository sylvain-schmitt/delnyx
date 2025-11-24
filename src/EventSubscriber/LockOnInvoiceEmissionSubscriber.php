<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Invoice;
use App\Entity\InvoiceStatus;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * EventSubscriber pour verrouiller les factures après émission
 * 
 * Conformité légale française : une facture émise (SENT, PAID, OVERDUE) 
 * ne peut plus être modifiée.
 */
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate')]
class LockOnInvoiceEmissionSubscriber
{
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Invoice) {
            $this->handleInvoiceEmission($entity, $args);
        }
    }

    private function handleInvoiceEmission(Invoice $invoice, LifecycleEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();
        $changeset = $uow->getEntityChangeSet($invoice);

        $statutEnum = $invoice->getStatutEnum();
        
        // Vérifier si la facture est émise (ne peut plus être modifiée)
        if ($statutEnum && $statutEnum->isEmitted()) {
            $this->preventModifications($invoice, $changeset);
        }
    }

    /**
     * Empêche les modifications d'une facture émise
     * 
     * @throws \RuntimeException si une tentative de modification est détectée
     */
    private function preventModifications(Invoice $invoice, array $changeset): void
    {
        // Champs autorisés à être modifiés même après émission
        $allowedFields = [
            'statut',           // Pour passer à PAID ou CANCELLED
            'datePaiement',     // Pour enregistrer le paiement
            'dateEnvoi',        // Pour enregistrer l'envoi
            'dateModification', // Horodatage automatique
            'pdpStatus',        // Statut PDP (facturation électronique)
            'pdpProvider',      // Provider PDP
            'pdpTransmissionDate', // Date transmission PDP
            'pdpResponse',      // Réponse PDP
            'pdfFilename',      // Nom du fichier PDF généré (technique, ne change pas le contenu)
            'pdfHash'           // Hash du PDF généré (technique, ne change pas le contenu)
        ];

        foreach ($changeset as $field => $change) {
            if (!in_array($field, $allowedFields)) {
                throw new \RuntimeException(
                    sprintf(
                        'La facture #%s est émise et ne peut plus être modifiée. Le champ "%s" ne peut pas être changé.',
                        $invoice->getNumero() ?? $invoice->getId(),
                        $field
                    )
                );
            }
        }
    }
}

