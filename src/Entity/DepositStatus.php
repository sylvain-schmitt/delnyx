<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Statut des accomptes
 */
enum DepositStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::PAID => 'Payé',
            self::CANCELLED => 'Annulé',
            self::REFUNDED => 'Remboursé',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'yellow',
            self::PAID => 'green',
            self::CANCELLED => 'gray',
            self::REFUNDED => 'blue',
        };
    }

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    public function canBeCancelled(): bool
    {
        return $this === self::PENDING;
    }

    public function canBeRefunded(): bool
    {
        return $this === self::PAID;
    }
}
