<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Client;
use App\Message\RegeneratePdfMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * EventSubscriber pour régénérer les PDF lorsque les informations du client changent
 * 
 * Déclenche la régénération automatique des PDF pour tous les documents émis/signés
 * lorsque les informations du client sont modifiées
 */
#[AsEntityListener(event: Events::postUpdate, entity: Client::class, method: 'postUpdate')]
class ClientUpdateSubscriber
{
    // Champs qui nécessitent une régénération des PDF
    private const FIELDS_REQUIRING_REGENERATION = [
        'nom',
        'prenom',
        'companyName',
        'email',
        'telephone',
        'adresse',
        'codePostal',
        'ville',
        'siren',
        'siret',
    ];

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger
    ) {
    }

    public function postUpdate(Client $entity, LifecycleEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();
        $changeset = $uow->getEntityChangeSet($entity);

        // Vérifier si un champ nécessitant une régénération a changé
        $needsRegeneration = false;
        foreach (self::FIELDS_REQUIRING_REGENERATION as $field) {
            if (isset($changeset[$field])) {
                $needsRegeneration = true;
                break;
            }
        }

        if (!$needsRegeneration) {
            return;
        }

        // Dispatcher la régénération PDF en arrière-plan
        try {
            $this->logger->info('Régénération PDF déclenchée par modification Client (en arrière-plan)', [
                'client_id' => $entity->getId(),
                'changed_fields' => array_keys($changeset),
            ]);

            // Dispatcher le message pour traitement asynchrone
            $this->messageBus->dispatch(new RegeneratePdfMessage('client', (string) $entity->getId()));
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du dispatch de la régénération PDF', [
                'client_id' => $entity->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}

