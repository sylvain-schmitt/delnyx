<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Enum pour les types de factures
 *
 * Types disponibles :
 * - STANDARD : Facture classique (travail terminé)
 * - DEPOSIT : Facture d'acompte (paiement anticipé)
 * - FINAL : Facture finale (avec déduction des acomptes)
 *
 * @package App\Entity
 */
enum InvoiceType: string
{
    case STANDARD = 'standard';
    case DEPOSIT = 'deposit';
    case FINAL = 'final';

    /**
     * Retourne le libellé du type
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::STANDARD => 'Facture',
            self::DEPOSIT => 'Facture d\'acompte',
            self::FINAL => 'Facture finale',
        };
    }

    /**
     * Retourne une couleur pour l'affichage
     */
    public function getColor(): string
    {
        return match ($this) {
            self::STANDARD => 'primary',
            self::DEPOSIT => 'info',
            self::FINAL => 'success',
        };
    }

    /**
     * Retourne les choix pour les formulaires
     */
    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case;
        }
        return $choices;
    }

    /**
     * Vérifie si c'est une facture d'acompte
     */
    public function isDeposit(): bool
    {
        return $this === self::DEPOSIT;
    }

    /**
     * Vérifie si c'est une facture finale
     */
    public function isFinal(): bool
    {
        return $this === self::FINAL;
    }
}
