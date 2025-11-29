<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\CompanySettings;
use App\Message\RegeneratePdfMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * EventSubscriber pour régénérer les PDF lorsque les informations de l'émetteur changent
 * 
 * Déclenche la régénération automatique des PDF pour tous les documents émis/signés
 * lorsque les informations de l'entreprise (CompanySettings) sont modifiées
 */
#[AsEntityListener(event: Events::postUpdate, entity: CompanySettings::class, method: 'postUpdate')]
class CompanySettingsUpdateSubscriber
{
    // Champs qui nécessitent une régénération des PDF
    private const FIELDS_REQUIRING_REGENERATION = [
        'raisonSociale',
        'adresse',
        'codePostal',
        'ville',
        'siren',
        'siret',
        'telephone',
        'email',
        'tauxTVADefaut', // Le taux de TVA peut affecter les montants affichés
        'logoPath', // Le logo de l'entreprise
    ];

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger
    ) {
    }

    public function postUpdate(CompanySettings $entity, LifecycleEventArgs $args): void
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
            $companyId = $entity->getCompanyId();
            if ($companyId) {
                $this->logger->info('Régénération PDF déclenchée par modification CompanySettings (en arrière-plan)', [
                    'company_id' => $companyId,
                    'changed_fields' => array_keys($changeset),
                ]);

                // Dispatcher le message pour traitement asynchrone
                $this->messageBus->dispatch(new RegeneratePdfMessage('company', $companyId));
            }
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du dispatch de la régénération PDF', [
                'company_id' => $entity->getCompanyId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}

