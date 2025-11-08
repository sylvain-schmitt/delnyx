<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Enum pour les statuts des avenants (amendments)
 */
enum AmendmentStatus: string
{
    case DRAFT = 'draft';
    case VALIDATED = 'validated';
    case REJECTED = 'rejected';
    case SENT = 'sent';

    /**
     * Retourne le libellé du statut
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::VALIDATED => 'Validé',
            self::REJECTED => 'Rejeté',
            self::SENT => 'Envoyé',
        };
    }

    /**
     * Retourne la couleur Bootstrap pour l'affichage
     */
    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'warning',
            self::VALIDATED => 'success',
            self::REJECTED => 'danger',
            self::SENT => 'info',
        };
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

