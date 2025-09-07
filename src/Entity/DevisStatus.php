<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Enum pour les statuts des devis
 */
enum DevisStatus: string
{
    case BROUILLON = 'brouillon';
    case ENVOYE = 'envoye';
    case ACCEPTE = 'accepte';
    case REFUSE = 'refuse';
    case EXPIRE = 'expire';

    /**
     * Retourne le libellé du statut
     */
    public function getLabel(): string
    {
        return match($this) {
            self::BROUILLON => 'Brouillon',
            self::ENVOYE => 'Envoyé',
            self::ACCEPTE => 'Accepté',
            self::REFUSE => 'Refusé',
            self::EXPIRE => 'Expiré',
        };
    }

    /**
     * Retourne la couleur Bootstrap pour l'affichage
     */
    public function getColor(): string
    {
        return match($this) {
            self::BROUILLON => 'secondary',
            self::ENVOYE => 'info',
            self::ACCEPTE => 'success',
            self::REFUSE => 'danger',
            self::EXPIRE => 'warning',
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
     * Retourne tous les statuts disponibles pour EasyAdmin
     */
    public static function getEasyAdminChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case;
        }
        return $choices;
    }
}
