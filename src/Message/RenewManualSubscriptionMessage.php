<?php

declare(strict_types=1);

namespace App\Message;

final class RenewManualSubscriptionMessage
{
    public function __construct(
        private readonly int $subscriptionId
    ) {}

    public function getSubscriptionId(): int
    {
        return $this->subscriptionId;
    }
}
