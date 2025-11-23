<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Enum pour le mode de facturation électronique (PDP - Plateforme de Dématérialisation Partenaire)
 */
enum PDPMode: string
{
    case NONE = 'none'; // Pas de facturation électronique
    case SANDBOX = 'sandbox'; // Mode test/sandbox
    case PRODUCTION = 'production'; // Mode production

    public function getLabel(): string
    {
        return match ($this) {
            self::NONE => 'Aucun',
            self::SANDBOX => 'Sandbox (Test)',
            self::PRODUCTION => 'Production',
        };
    }

    public function isEnabled(): bool
    {
        return $this !== self::NONE;
    }
}

