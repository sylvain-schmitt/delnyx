<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Enum pour les statuts des factures (invoices)
 * 
 * Workflow légal français :
 * - DRAFT : Brouillon, modifiable
 * - ISSUED : Émise, immuable (document légal)
 * - SENT : Envoyée au client, immuable
 * - PAID : Payée, immuable
 * - CANCELLED : Annulée (via avoir total), immuable
 * 
 * @package App\Entity
 */
enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
    case SENT = 'sent';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';

    /**
     * Retourne le libellé du statut
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::ISSUED => 'Émise',
            self::SENT => 'Envoyée',
            self::PAID => 'Payée',
            self::CANCELLED => 'Annulée',
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
            self::SENT => 'primary',
            self::PAID => 'success',
            self::CANCELLED => 'dark',
        };
    }

    /**
     * Retourne les choix pour EasyAdmin
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
     * Détermine si la facture est modifiable
     * Conformité légale : seule une facture en brouillon est modifiable
     */
    public function isModifiable(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Détermine si la facture est émise (immutable)
     * Une facture émise ne peut plus être modifiée (conformité légale française)
     */
    public function isEmitted(): bool
    {
        return in_array($this, [self::ISSUED, self::SENT, self::PAID, self::CANCELLED]);
    }

    /**
     * Détermine si la facture est finale (non modifiable)
     */
    public function isFinal(): bool
    {
        return $this->isEmitted();
    }

    /**
     * Vérifie si la facture peut être émise
     * DRAFT → ISSUED
     */
    public function canBeIssued(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Vérifie si la facture peut être envoyée
     * Peut être envoyée sauf si DRAFT ou CANCELLED
     */
    public function canBeSent(): bool
    {
        return !in_array($this, [self::DRAFT, self::CANCELLED]);
    }

    /**
     * Vérifie si la facture peut être marquée comme payée
     * ISSUED ou SENT → PAID
     */
    public function canBeMarkedPaid(): bool
    {
        return in_array($this, [self::ISSUED, self::SENT]);
    }

    /**
     * Vérifie si un avoir peut être créé pour cette facture
     * ISSUED, SENT ou PAID → CreditNote
     */
    public function canCreateCreditNote(): bool
    {
        return in_array($this, [self::ISSUED, self::SENT, self::PAID]);
    }

    /**
     * Vérifie si la facture peut être annulée
     * DRAFT peut être annulée manuellement
     * ISSUED/PAID doivent être annulées via avoir total
     */
    public function canBeCancelled(): bool
    {
        return $this === self::DRAFT; // Seulement les brouillons peuvent être annulés manuellement
    }

    /**
     * Vérifie si la facture peut être supprimée
     * Jamais (archivage 10 ans obligatoire)
     */
    public function canBeDeleted(): bool
    {
        return false; // Archivage 10 ans obligatoire
    }
}
