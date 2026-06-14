<?php

declare(strict_types=1);

namespace App\Entity;

enum ClientCategory: string
{
    case PARTICULIER   = 'particulier';
    case PROFESSIONNEL = 'professionnel';

    public function getLabel(): string
    {
        return match ($this) {
            self::PARTICULIER   => 'Particulier',
            self::PROFESSIONNEL => 'Professionnel (entreprise)',
        };
    }

    /** B2B → e-invoicing par facture via PA */
    public function isB2B(): bool
    {
        return $this === self::PROFESSIONNEL;
    }

    /** B2C → e-reporting agrégé mensuel via PA */
    public function isB2C(): bool
    {
        return $this === self::PARTICULIER;
    }

    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case;
        }
        return $choices;
    }
}
