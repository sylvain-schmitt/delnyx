<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Event subscriber pour retirer automatiquement les lignes vides des formulaires de documents
 * 
 * Cet event subscriber filtre les lignes (QuoteLine, InvoiceLine, AmendmentLine, CreditNoteLine)
 * qui n'ont pas de données saisies (description vide, prix unitaire null, etc.)
 * avant que le formulaire ne soit validé.
 */
class RemoveEmptyLinesSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SUBMIT => 'onPreSubmit',
        ];
    }

    public function onPreSubmit(FormEvent $event): void
    {
        $data = $event->getData();

        // Vérifier si le champ 'lines' existe dans les données soumises
        if (!isset($data['lines']) || !is_array($data['lines'])) {
            return;
        }

        // Filtrer les lignes vides
        $data['lines'] = array_filter($data['lines'], function ($line) {
            // Une ligne est considérée comme vide si :
            // - description est vide/null
            // - ET unitPrice est vide/null
            // - ET quantity est vide/null/0
            
            $hasDescription = !empty($line['description'] ?? '');
            $hasUnitPrice = !empty($line['unitPrice'] ?? '');
            // On ignore la quantité car elle a souvent une valeur par défaut (1)
            // $hasQuantity = !empty($line['quantity'] ?? '');
            
            // Garder la ligne si au moins la description ou le prix est rempli
            return $hasDescription || $hasUnitPrice;
        });

        // Réindexer le tableau pour éviter les trous dans les index
        $data['lines'] = array_values($data['lines']);

        $event->setData($data);
    }
}
