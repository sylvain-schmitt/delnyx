<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Enum pour les statuts des clients
 */
enum ClientStatus: string
{
    case ACTIF = 'actif';
    case INACTIF = 'inactif';
    case PROSPECT = 'prospect';

    /**
     * Retourne le libellé du statut
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIF => 'Actif',
            self::INACTIF => 'Inactif',
            self::PROSPECT => 'Prospect',
        };
    }

    /**
     * Retourne la couleur Bootstrap pour l'affichage
     */
    public function getColor(): string
    {
        return match ($this) {
            self::ACTIF => 'success',
            self::INACTIF => 'secondary',
            self::PROSPECT => 'warning',
        };
    }

    /**
     * Retourne tous les statuts disponibles pour les formulaires
     */
    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case->value;
        }
        return $choices;
    }

    /**
     * Retourne tous les statuts disponibles pour les formulaires
     */
    public static function getChoicesForForm(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case;
        }
        return $choices;
    }
}
