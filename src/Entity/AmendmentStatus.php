<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Enum pour les statuts des avenants (amendments)
 */
enum AmendmentStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case SIGNED = 'signed';
    case CANCELLED = 'cancelled';

    /**
     * Retourne le libellé du statut
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::SENT => 'Envoyé',
            self::SIGNED => 'Signé',
            self::CANCELLED => 'Annulé',
        };
    }

    /**
     * Retourne la couleur Bootstrap pour l'affichage
     */
    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'warning',
            self::SENT => 'info',
            self::SIGNED => 'success',
            self::CANCELLED => 'dark',
        };
    }

    /**
     * Vérifie si l'avenant est dans un état final (ne peut plus être modifié)
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::SIGNED, self::CANCELLED]);
    }

    /**
     * Vérifie si l'avenant peut être modifié
     * DRAFT et SENT peuvent être modifiés (selon workflow légal)
     */
    public function isModifiable(): bool
    {
        return in_array($this, [self::DRAFT, self::SENT]);
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

