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
    case ISSUED = 'issued';
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
            self::ISSUED => 'Émis',
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
            self::ISSUED => 'info',
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
     * États finaux : ISSUED, SIGNED, REFUSED, EXPIRED, CANCELLED
     * Note : ACCEPTED n'est PAS un état final car il reste modifiable
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::ISSUED, self::SIGNED, self::REFUSED, self::EXPIRED, self::CANCELLED]);
    }

    /**
     * Détermine si le devis est modifiable selon les règles légales
     * Modifiable : DRAFT uniquement
     * Non modifiable : ISSUED, SENT, SIGNED, ACCEPTED, REFUSED, EXPIRED, CANCELLED
     */
    public function isModifiable(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Détermine si le devis est émis (immutable)
     * États émis : ISSUED, SENT, SIGNED, ACCEPTED, REFUSED, EXPIRED, CANCELLED
     */
    public function isEmitted(): bool
    {
        return in_array($this, [self::ISSUED, self::SENT, self::SIGNED, self::ACCEPTED, self::REFUSED, self::EXPIRED, self::CANCELLED]);
    }

    /**
     * Vérifie si le devis peut être émis
     * DRAFT → ISSUED
     */
    public function canBeIssued(): bool
    {
        return $this === self::DRAFT;
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
     * Peut être envoyé sauf si DRAFT, REFUSED, EXPIRED, CANCELLED
     */
    public function canBeSent(): bool
    {
        return !in_array($this, [self::DRAFT, self::REFUSED, self::EXPIRED, self::CANCELLED]);
    }

    /**
     * Vérifie si le devis peut être accepté
     * SENT → ACCEPTED
     */
    public function canBeAccepted(): bool
    {
        return $this === self::SENT;
    }

    /**
     * Vérifie si le devis peut être signé
     * SENT → SIGNED ou ACCEPTED → SIGNED
     */
    public function canBeSigned(): bool
    {
        return in_array($this, [self::SENT, self::ACCEPTED]);
    }

    /**
     * Vérifie si le devis peut être annulé
     * DRAFT uniquement → CANCELLED
     */
    public function canBeCancelled(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Vérifie si le devis peut être refusé
     * SENT, ACCEPTED → REFUSED
     */
    public function canBeRefused(): bool
    {
        return in_array($this, [self::SENT, self::ACCEPTED]);
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
