<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Enum pour les statuts des avenants (amendments)
 */
enum AmendmentStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
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
            self::ISSUED => 'Émis',
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
            self::ISSUED => 'info',
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
        return in_array($this, [self::ISSUED, self::SIGNED, self::CANCELLED]);
    }

    /**
     * Vérifie si l'avenant peut être modifié
     * DRAFT uniquement peut être modifié
     */
    public function isModifiable(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Détermine si l'avenant est émis (immutable)
     */
    public function isEmitted(): bool
    {
        return in_array($this, [self::ISSUED, self::SENT, self::SIGNED, self::CANCELLED]);
    }

    /**
     * Vérifie si l'avenant peut être émis
     * DRAFT → ISSUED
     */
    public function canBeIssued(): bool
    {
        return $this === self::DRAFT;
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

    /**
     * Vérifie si l'avenant peut être envoyé
     * Peut être envoyé sauf si DRAFT ou CANCELLED
     */
    public function canBeSent(): bool
    {
        return !in_array($this, [self::DRAFT, self::CANCELLED]);
    }
}

