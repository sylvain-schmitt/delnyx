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
    case REFUNDED = 'refunded';
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
            self::REFUNDED => 'Remboursé',
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
            self::REFUNDED => 'success',
            self::CANCELLED => 'dark',
        };
    }

    /**
     * Vérifie si l'avoir est dans un état final (ne peut plus être modifié)
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::ISSUED, self::SENT, self::REFUNDED, self::CANCELLED]);
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
        return in_array($this, [self::ISSUED, self::SENT, self::REFUNDED]);
    }

    /**
     * Vérifie si l'avoir peut être envoyé
     * DRAFT → SENT (direct) ou ISSUED → SENT ou SENT → SENT (relance)
     * Ne peut pas être envoyé si CANCELLED ou REFUNDED
     */
    public function canBeSent(): bool
    {
        return !in_array($this, [self::CANCELLED, self::REFUNDED]);
    }

    /**
     * Vérifie si l'avoir peut être annulé
     * SEULEMENT DRAFT peut être annulé directement
     * ISSUED/SENT doivent être annulés via avoir total (document comptable légal)
     */
    public function canBeCancelled(): bool
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
}
