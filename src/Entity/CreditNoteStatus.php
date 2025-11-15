<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Enum pour les statuts des avoirs (credit notes)
 */
enum CreditNoteStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';

    /**
     * Retourne le libellé du statut
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::ISSUED => 'Émis',
        };
    }

    /**
     * Retourne la couleur Bootstrap pour l'affichage
     */
    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'warning',
            self::ISSUED => 'success',
        };
    }

    /**
     * Vérifie si l'avoir est dans un état final (ne peut plus être modifié)
     */
    public function isFinal(): bool
    {
        return $this === self::ISSUED;
    }

    /**
     * Vérifie si l'avoir peut être modifié
     */
    public function isModifiable(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Vérifie si l'avoir est émis
     */
    public function isEmitted(): bool
    {
        return $this === self::ISSUED;
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

