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
    case SENT = 'sent';
    case APPLIED = 'applied';
    case CANCELLED = 'cancelled';

    /**
     * Retourne le libellé du statut
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::ISSUED => 'Émis',
            self::SENT => 'Envoyé',
            self::APPLIED => 'Appliqué',
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
            self::ISSUED => 'info',
            self::SENT => 'primary',
            self::APPLIED => 'success',
            self::CANCELLED => 'dark',
        };
    }

    /**
     * Vérifie si l'avoir est dans un état final (ne peut plus être modifié)
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::ISSUED, self::SENT, self::APPLIED, self::CANCELLED]);
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
        return in_array($this, [self::ISSUED, self::SENT, self::APPLIED]);
    }

    /**
     * Vérifie si l'avoir peut être envoyé
     * Peut être envoyé sauf si DRAFT ou CANCELLED
     */
    public function canBeSent(): bool
    {
        return !in_array($this, [self::DRAFT, self::CANCELLED]);
    }

    /**
     * Vérifie si l'avoir peut être annulé
     * Un avoir peut être annulé s'il est en brouillon (DRAFT) ou émis (ISSUED)
     */
    public function canBeCancelled(): bool
    {
        return in_array($this, [self::DRAFT, self::ISSUED]);
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
