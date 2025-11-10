<?php

namespace App\Entity;

/**
 * Enum pour les statuts des devis (quotes)
 * 
 * @package App\Entity
 */
enum QuoteStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case SIGNED = 'signed';
    case ACCEPTED = 'accepted';
    case REFUSED = 'refused';
    case EXPIRED = 'expired';
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
            self::ACCEPTED => 'Accepté',
            self::REFUSED => 'Refusé',
            self::EXPIRED => 'Expiré',
            self::CANCELLED => 'Annulé',
        };
    }

    /**
     * Retourne la couleur Bootstrap pour l'affichage
     */
    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::SENT => 'info',
            self::SIGNED => 'primary',
            self::ACCEPTED => 'success',
            self::REFUSED => 'danger',
            self::EXPIRED => 'warning',
            self::CANCELLED => 'dark',
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

    /**
     * Retourne les choix pour EasyAdmin (retourne les enums directement)
     */
    public static function getEasyAdminChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case;
        }
        return $choices;
    }

    /**
     * Vérifie si le devis est dans un état final (ne peut plus être modifié)
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::SIGNED, self::ACCEPTED, self::REFUSED, self::EXPIRED, self::CANCELLED]);
    }

    /**
     * Vérifie si le devis peut être envoyé
     */
    public function canBeSent(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Vérifie si le devis peut être accepté
     */
    public function canBeAccepted(): bool
    {
        return $this === self::SENT;
    }

    /**
     * Détermine si le devis est modifiable
     * Un devis n'est modifiable que s'il est en brouillon
     */
    public function isModifiable(): bool
    {
        return $this === self::DRAFT || $this === self::SENT;
    }

    /**
     * Détermine si le devis est émis (envoyé, accepté, refusé, expiré, annulé)
     */
    public function isEmitted(): bool
    {
        return in_array($this, [self::SENT, self::SIGNED, self::ACCEPTED, self::REFUSED, self::EXPIRED, self::CANCELLED]);
    }
}

