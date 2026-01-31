<?php

declare(strict_types=1);

namespace App\Entity;

enum AppointmentStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::CONFIRMED => 'Confirmé',
            self::CANCELLED => 'Annulé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'amber',
            self::CONFIRMED => 'green',
            self::CANCELLED => 'red',
        };
    }
}
