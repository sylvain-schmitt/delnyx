<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Entity\QuoteStatus;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * EventSubscriber pour empêcher la modification des lignes d'un devis signé
 * 
 * Règle légale : Un devis SIGNED est un contrat et devient immuable.
 * Les lignes ne peuvent plus être modifiées, ajoutées ou supprimées.
 * 
 * @package App\EventSubscriber
 */
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate')]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove')]
class PreventQuoteLineModificationSubscriber
{
    /**
     * Empêche la modification d'une ligne si le devis est signé
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof QuoteLine) {
            $this->preventLineModification($entity);
        }
    }

    /**
     * Empêche la suppression d'une ligne si le devis est signé
     */
    public function preRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof QuoteLine) {
            $this->preventLineDeletion($entity);
        }
    }

    /**
     * Vérifie et empêche la modification d'une ligne
     * 
     * @throws \RuntimeException si le devis est signé
     */
    private function preventLineModification(QuoteLine $line): void
    {
        $quote = $line->getQuote();

        if ($quote === null) {
            return;
        }

        $status = $quote->getStatut();

        // Si le devis est dans un état final (SIGNED, REFUSED, EXPIRED, CANCELLED), bloquer
        if ($status !== null && $status->isFinal()) {
            throw new \RuntimeException(
                sprintf(
                    'Impossible de modifier une ligne du devis #%s car il est %s et ne peut plus être modifié.',
                    $quote->getNumero() ?? $quote->getId(),
                    $status->getLabel()
                )
            );
        }
    }

    /**
     * Vérifie et empêche la suppression d'une ligne
     * 
     * @throws \RuntimeException si le devis est signé
     */
    private function preventLineDeletion(QuoteLine $line): void
    {
        $quote = $line->getQuote();

        if ($quote === null) {
            return;
        }

        $status = $quote->getStatut();

        // Si le devis est dans un état final, bloquer
        if ($status !== null && $status->isFinal()) {
            throw new \RuntimeException(
                sprintf(
                    'Impossible de supprimer une ligne du devis #%s car il est %s et ne peut plus être modifié.',
                    $quote->getNumero() ?? $quote->getId(),
                    $status->getLabel()
                )
            );
        }
    }
}

