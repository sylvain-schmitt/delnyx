<?php

namespace App\EventListener;

use App\Entity\Technology;
use App\Service\IconImportService;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;

class TechnologyIconListener
{
    public function __construct(
        private IconImportService $iconImportService
    ) {}

    /**
     * Appelé après la création d'une technologie
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($entity instanceof Technology && $entity->getIcone()) {
            $this->iconImportService->importIcon($entity->getIcone());
        }
    }

    /**
     * Appelé après la mise à jour d'une technologie
     */
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($entity instanceof Technology && $entity->getIcone()) {
            $this->iconImportService->importIcon($entity->getIcone());
        }
    }
}
