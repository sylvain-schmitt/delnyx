<?php

declare(strict_types=1);

namespace App\Message;

class SyncPurchaseInvoicesMessage
{
    public function __construct(private ?string $companyId = null) {}

    public function getCompanyId(): ?string
    {
        return $this->companyId;
    }
}
