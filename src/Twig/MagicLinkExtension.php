<?php

namespace App\Twig;

use App\Entity\Quote;
use App\Entity\Amendment;
use App\Entity\Invoice;
use App\Entity\CreditNote;
use App\Service\MagicLinkService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Extension Twig pour générer des magic links depuis les templates
 */
class MagicLinkExtension extends AbstractExtension
{
    public function __construct(
        private MagicLinkService $magicLinkService
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('magic_link', [$this, 'generateMagicLink']),
        ];
    }

    /**
     * Génère un magic link pour une entité et une action
     * 
     * Usage dans Twig :
     *   {{ magic_link(quote, 'view') }}
     *   {{ magic_link(invoice, 'pay') }}
     */
    public function generateMagicLink(
        Quote|Amendment|Invoice|CreditNote $document,
        string $action
    ): string {
        return $this->magicLinkService->generatePublicLink($document, $action);
    }
}
