<?php

declare(strict_types=1);

namespace App\Form\EventSubscriber;

use App\Entity\Quote;
use App\Entity\InvoiceLine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Subscriber pour mettre à jour dynamiquement le formulaire de facture
 * lorsque un devis est sélectionné
 */
class InvoiceQuoteSubscriber implements EventSubscriberInterface
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
        $data = $event->getData();
        $form = $event->getForm();

        // Récupérer l'ID du devis depuis les données soumises
        $quoteId = null;
        if (isset($data['quote'])) {
            $quoteValue = $data['quote'];
            
            if (is_numeric($quoteValue)) {
                $quoteId = (int)$quoteValue;
            } elseif (is_string($quoteValue) && is_numeric($quoteValue)) {
                $quoteId = (int)$quoteValue;
            }
        }

        // Si on a un ID de devis, charger le devis
        if ($quoteId) {
            $quote = $this->entityManager->getRepository(Quote::class)->find($quoteId);
            
            if ($quote) {
                // Mettre à jour le client si non défini ou différent
                if (isset($data['client']) && empty($data['client']) && $quote->getClient()) {
                    $data['client'] = $quote->getClient()->getId();
                }

                // Pré-remplir les champs depuis le devis si vides
                if (isset($data['conditionsPaiement']) && empty($data['conditionsPaiement'])) {
                    $data['conditionsPaiement'] = $quote->getConditionsPaiement();
                }
                
                if (isset($data['delaiPaiement']) && empty($data['delaiPaiement'])) {
                    $data['delaiPaiement'] = $quote->getDelaiLivraison(); // Mapping approximatif, à vérifier
                }

                // Note: Les lignes sont gérées par le JS qui les ajoute au DOM, 
                // mais on doit s'assurer que le formulaire accepte ces nouvelles lignes
                // Le InvoiceType gère déjà l'ajout dynamique des lignes dans son PRE_SUBMIT
                // via la boucle sur les données soumises.
                
                // On met à jour les données modifiées
                $event->setData($data);
            }
        }
    }
}
