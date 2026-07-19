<?php

namespace App\Message;

class NotifyPdpPaymentMessage
{
    public function __construct(
        public readonly int $invoiceId,
    ) {
    }
}
