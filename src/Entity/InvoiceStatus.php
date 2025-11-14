<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Enum pour les statuts des factures (invoices)
 */
enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';

    /**
     * Retourne le libellé du statut
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::SENT => 'Envoyée',
            self::PAID => 'Payée',
            self::OVERDUE => 'En retard',
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
            self::SENT => 'info',
            self::PAID => 'success',
            self::OVERDUE => 'danger',
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
     * Détermine si la facture est émise (envoyée, payée, en retard, annulée)
     */
    public function isEmitted(): bool
    {
        return in_array($this, [self::SENT, self::PAID, self::OVERDUE, self::CANCELLED]);
    }
}

