<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Enum pour les statuts des factures
 */
enum FactureStatus: string
{
    case BROUILLON = 'brouillon';
    case ENVOYEE = 'envoyee';
    case PAYEE = 'payee';
    case EN_RETARD = 'en_retard';
    case ANNULEE = 'annulee';

    /**
     * Retourne le libellé du statut
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::BROUILLON => 'Brouillon',
            self::ENVOYEE => 'Envoyée',
            self::PAYEE => 'Payée',
            self::EN_RETARD => 'En retard',
            self::ANNULEE => 'Annulée',
        };
    }

    /**
     * Retourne la couleur Bootstrap pour l'affichage
     */
    public function getColor(): string
    {
        return match ($this) {
            self::BROUILLON => 'secondary',
            self::ENVOYEE => 'info',
            self::PAYEE => 'success',
            self::EN_RETARD => 'danger',
            self::ANNULEE => 'dark',
        };
    }

    /**
     * Retourne les choix pour EasyAdmin
     */
    public static function getEasyAdminChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case->value;
        }
        return $choices;
    }

    /**
     * Retourne les choix pour les formulaires
     */
    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case->value;
        }
        return $choices;
    }
}
