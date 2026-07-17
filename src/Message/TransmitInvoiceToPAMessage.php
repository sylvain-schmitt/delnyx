<?php

declare(strict_types=1);

namespace App\Message;

class TransmitInvoiceToPAMessage
{
    public function __construct(private int $invoiceId) {}

    public function getInvoiceId(): int
    {
        return $this->invoiceId;
    }
}
