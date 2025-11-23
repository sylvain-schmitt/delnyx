<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Quote;
use App\Service\QuoteService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * EventSubscriber pour expirer automatiquement un devis à la lecture
 * si sa date de validité est dépassée
 * 
 * Cette vérification complète la commande cron/scheduler qui expire
 * les devis en masse. Ici, on expire à la volée lors de la lecture.
 * 
 * @package App\EventSubscriber
 */
#[AsEntityListener(event: Events::postLoad, method: 'postLoad')]
class AutoExpireQuoteSubscriber
{
    public function __construct(
        private readonly QuoteService $quoteService,
    ) {
    }

    /**
     * Vérifie et expire un devis à la lecture si nécessaire
     */
    public function postLoad(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Quote) {
            // Vérifier si le devis doit être expiré
            $this->quoteService->expireIfNeeded($entity);
        }
    }
}

