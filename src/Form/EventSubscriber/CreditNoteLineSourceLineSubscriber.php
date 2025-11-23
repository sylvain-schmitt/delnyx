<?php

declare(strict_types=1);

namespace App\Form\EventSubscriber;

use App\Entity\CreditNote;
use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Subscriber pour mettre à jour dynamiquement le query_builder du champ sourceLine
 * dans les lignes d'avoir, basé sur la facture sélectionnée dans le formulaire parent
 */
class CreditNoteLineSourceLineSubscriber implements EventSubscriberInterface
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

        // Récupérer le formulaire root (CreditNote)
        $rootForm = $form->getRoot();
        if (!$rootForm->has('invoice')) {
            return;
        }

        $invoiceId = null;
        
        // Dans PRE_SUBMIT, les données ne sont pas encore mappées
        // Récupérer la facture depuis les données soumises brutes du formulaire root
        // On doit utiliser getParent() pour remonter jusqu'au formulaire root
        $parentForm = $form->getParent()?->getParent(); // lines -> CreditNote
        if ($parentForm && $parentForm->has('invoice')) {
            // Récupérer les données soumises brutes depuis le formulaire parent
            $parentSubmittedData = $parentForm->getData();
            if ($parentSubmittedData && method_exists($parentSubmittedData, 'getInvoice') && $parentSubmittedData->getInvoice()) {
                $invoiceId = $parentSubmittedData->getInvoice()->getId();
            }
        }
        
        // Si pas de facture, essayer depuis le champ invoice du formulaire root
        if (!$invoiceId) {
            $invoiceField = $rootForm->get('invoice');
            $invoiceValue = $invoiceField->getData();
            
            if ($invoiceValue instanceof Invoice) {
                $invoiceId = $invoiceValue->getId();
            } elseif (is_numeric($invoiceValue)) {
                $invoiceId = (int)$invoiceValue;
            }
        }

        // Si on a un ID de facture, mettre à jour le query_builder du champ sourceLine
        if ($invoiceId && $form->has('sourceLine')) {
            $sourceLineField = $form->get('sourceLine');
            $options = $sourceLineField->getConfig()->getOptions();
            
            // Créer un nouveau query_builder avec la facture sélectionnée
            $options['query_builder'] = function ($er) use ($invoiceId) {
                return $er->createQueryBuilder('il')
                    ->where('il.invoice = :invoice')
                    ->setParameter('invoice', $invoiceId)
                    ->orderBy('il.id', 'ASC');
            };

            // Reconstruire le champ avec les nouvelles options
            $form->remove('sourceLine');
            $form->add('sourceLine', EntityType::class, $options);
        }
    }
}
