<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Enum des providers de paiement supportés
 */
enum PaymentProvider: string
{
    case STRIPE = 'stripe';
    case PAYPAL = 'paypal';
    case MANUAL = 'manual'; // Virement, chèque, etc.

    public function label(): string
    {
        return match($this) {
            self::STRIPE => 'Stripe (Carte bancaire)',
            self::PAYPAL => 'PayPal',
            self::MANUAL => 'Paiement manuel (Virement/Chèque)',
        };
    }
}
