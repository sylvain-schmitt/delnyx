<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message pour envoyer une relance de facture via Messenger
 */
class SendReminderMessage
{
    public function __construct(
        private int $invoiceId,
        private int $ruleId
    ) {}

    public function getInvoiceId(): int
    {
        return $this->invoiceId;
    }

    public function getRuleId(): int
    {
        return $this->ruleId;
    }
}
