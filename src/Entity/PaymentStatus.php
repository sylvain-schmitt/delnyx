<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Enum des statuts de paiement
 */
enum PaymentStatus: string
{
    case PENDING = 'pending';       // En attente (checkout créé mais pas validé)
    case SUCCEEDED = 'succeeded';   // Paiement réussi
    case FAILED = 'failed';         // Paiement échoué
    case CANCELLED = 'cancelled';   // Paiement annulé par l'utilisateur
    case REFUNDED = 'refunded';     // Paiement remboursé

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::SUCCEEDED => 'Payé',
            self::FAILED => 'Échoué',
            self::CANCELLED => 'Annulé',
            self::REFUNDED => 'Remboursé',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::PENDING => 'bg-yellow-500/20 text-yellow-300',
            self::SUCCEEDED => 'bg-green-500/20 text-green-300',
            self::FAILED => 'bg-red-500/20 text-red-300',
            self::CANCELLED => 'bg-gray-500/20 text-gray-300',
            self::REFUNDED => 'bg-orange-500/20 text-orange-300',
        };
    }
}
