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
    case REFUSED = 'refused';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    
    // Statuts SUPPRIMÉS pour workflow simplifié :
    // - ISSUED : Redondant (DRAFT → SENT direct)
    // - ACCEPTED : Doublon avec SIGNED (en France, accepté = signé)

    /**
     * Retourne le libellé du statut
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::SENT => 'Envoyé',
            self::SIGNED => 'Signé',
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
     * Retourne les choix pour les formulaires (retourne les enums directement)
     */
    public static function getChoicesForForm(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case;
        }
        return $choices;
    }

    /**
     * Vérifie si le devis est dans un état final (ne peut plus être modifié)
     * États finaux : SIGNED, REFUSED, EXPIRED, CANCELLED
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::SIGNED, self::REFUSED, self::EXPIRED, self::CANCELLED]);
    }

    /**
     * Détermine si le devis est modifiable selon les règles légales
     * Modifiable : DRAFT uniquement
     * Non modifiable : SENT, SIGNED, REFUSED, EXPIRED, CANCELLED
     */
    public function isModifiable(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Détermine si le devis est émis/envoyé (immutable)
     * États émis : SENT, SIGNED, REFUSED, EXPIRED, CANCELLED
     */
    public function isEmitted(): bool
    {
        return in_array($this, [self::SENT, self::SIGNED, self::REFUSED, self::EXPIRED, self::CANCELLED]);
    }

    /**
     * Détermine si le devis est contractuel (signé = contrat)
     * Seul SIGNED est contractuel en France
     */
    public function isContractual(): bool
    {
        return $this === self::SIGNED;
    }

    /**
     * Vérifie si le devis peut générer une facture
     * Seul un devis SIGNED peut être facturé (règle légale)
     */
    public function canGenerateInvoice(): bool
    {
        return $this === self::SIGNED;
    }

    /**
     * Vérifie si le devis peut être envoyé
     * Peut être envoyé depuis DRAFT ou SENT (renvoyer)
     * Ne peut PAS être envoyé si SIGNED, REFUSED, EXPIRED, CANCELLED
     */
    public function canBeSent(): bool
    {
        return !in_array($this, [self::SIGNED, self::REFUSED, self::EXPIRED, self::CANCELLED]);
    }

    /**
     * Vérifie si le devis peut être signé
     * Workflow simplifié : SENT → SIGNED directement
     */
    public function canBeSigned(): bool
    {
        return $this === self::SENT;
    }

    /**
     * Vérifie si le devis peut être annulé
     * DRAFT ou SENT → CANCELLED
     */
    public function canBeCancelled(): bool
    {
        return in_array($this, [self::DRAFT, self::SENT]);
    }

    /**
     * Vérifie si le devis peut être refusé par le client
     * SENT → REFUSED
     */
    public function canBeRefused(): bool
    {
        return $this === self::SENT;
    }

    /**
     * Vérifie si le devis peut être supprimé
     * Aucun devis ne peut être supprimé (archivage 10 ans obligatoire)
     */
    public function canBeDeleted(): bool
    {
        return false; // Jamais supprimable, archivage 10 ans obligatoire
    }
}
