<?php

declare(strict_types=1);

namespace App\Message;

class NotifyPdpCreditNoteReimbursedMessage
{
    public function __construct(public readonly int $creditNoteId) {}
}
