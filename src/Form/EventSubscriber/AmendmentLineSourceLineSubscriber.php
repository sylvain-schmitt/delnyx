<?php

declare(strict_types=1);

namespace App\Form\EventSubscriber;

use App\Entity\Quote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Subscriber pour mettre à jour dynamiquement le query_builder du champ sourceLine
 * dans les lignes d'avenant, basé sur le devis sélectionné dans le formulaire parent
 */
class AmendmentLineSourceLineSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SUBMIT => 'onPreSubmit',
        ];
    }

    public function onPreSubmit(FormEvent $event): void
    {
        $form = $event->getForm();
        $lineData = $event->getData();

        // Récupérer le formulaire root (Amendment)
        $rootForm = $form->getRoot();
        if (!$rootForm->has('quote')) {
            return;
        }

        $quoteId = null;
        
        // Dans PRE_SUBMIT, les données ne sont pas encore mappées
        // Récupérer le devis depuis les données soumises brutes du formulaire root
        // On doit utiliser getParent() pour remonter jusqu'au formulaire root
        $parentForm = $form->getParent()?->getParent(); // lines -> Amendment
        if ($parentForm && $parentForm->has('quote')) {
            // Récupérer les données soumises brutes depuis le formulaire parent
            $parentSubmittedData = $parentForm->getData();
            if ($parentSubmittedData && method_exists($parentSubmittedData, 'getQuote') && $parentSubmittedData->getQuote()) {
                $quoteId = $parentSubmittedData->getQuote()->getId();
            }
        }
        
        // Si pas de devis, essayer depuis le champ quote du formulaire root
        if (!$quoteId) {
            $quoteField = $rootForm->get('quote');
            $quoteValue = $quoteField->getData();
            
            if ($quoteValue instanceof Quote) {
                $quoteId = $quoteValue->getId();
            } elseif (is_numeric($quoteValue)) {
                $quoteId = (int)$quoteValue;
            }
        }

        // Debug: voir ce qui est récupéré
        if ($form->has('sourceLine') && isset($lineData['sourceLine'])) {
            \dump([
                'line_data' => $lineData,
                'sourceLine_value' => $lineData['sourceLine'] ?? null,
                'quoteId_found' => $quoteId,
                'rootForm_has_quote' => $rootForm->has('quote'),
                'quoteField_data' => $rootForm->has('quote') ? $rootForm->get('quote')->getData() : null,
                'parentForm_data' => $parentForm?->getData(),
            ]);
        }

        // Si on a un ID de devis, mettre à jour le query_builder du champ sourceLine
        if ($quoteId && $form->has('sourceLine')) {
            $sourceLineField = $form->get('sourceLine');
            $options = $sourceLineField->getConfig()->getOptions();
            
            // Créer un nouveau query_builder avec le devis sélectionné
            $options['query_builder'] = function ($er) use ($quoteId) {
                return $er->createQueryBuilder('ql')
                    ->where('ql.quote = :quote')
                    ->setParameter('quote', $quoteId)
                    ->orderBy('ql.id', 'ASC');
            };

            // Reconstruire le champ avec les nouvelles options
            $form->remove('sourceLine');
            $form->add('sourceLine', EntityType::class, $options);
        }
    }
}

