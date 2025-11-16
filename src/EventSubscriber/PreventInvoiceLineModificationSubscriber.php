<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\InvoiceLine;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * EventSubscriber pour empêcher la modification des lignes d'une facture émise
 * 
 * Conformité légale française : les lignes d'une facture émise sont immuables
 * 
 * @package App\EventSubscriber
 */
#[AsDoctrineListener(event: Events::preUpdate, priority: 500)]
#[AsDoctrineListener(event: Events::preRemove, priority: 500)]
class PreventInvoiceLineModificationSubscriber
{
    /**
     * Empêche la modification d'une ligne de facture si la facture est émise
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof InvoiceLine) {
            return;
        }

        $invoice = $entity->getInvoice();
        if ($invoice && $invoice->getStatutEnum()?->isEmitted()) {
            throw new AccessDeniedHttpException(
                sprintf(
                    'Les lignes de la facture #%s ne peuvent pas être modifiées car elle est émise.',
                    $invoice->getNumero() ?? 'N/A'
                )
            );
        }
    }

    /**
     * Empêche la suppression d'une ligne de facture si la facture est émise
     */
    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof InvoiceLine) {
            return;
        }

        $invoice = $entity->getInvoice();
        if ($invoice && $invoice->getStatutEnum()?->isEmitted()) {
            throw new AccessDeniedHttpException(
                sprintf(
                    'Les lignes de la facture #%s ne peuvent pas être supprimées car elle est émise.',
                    $invoice->getNumero() ?? 'N/A'
                )
            );
        }
    }
}

