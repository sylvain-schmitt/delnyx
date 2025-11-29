<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message pour régénérer les PDF en arrière-plan
 * 
 * Utilisé lorsque les informations de l'émetteur ou du client changent
 */
class RegeneratePdfMessage
{
    public function __construct(
        private readonly string $type, // 'company' ou 'client'
        private readonly string $identifier, // companyId ou clientId
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}

