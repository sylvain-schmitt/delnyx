<?php

declare(strict_types=1);

namespace App\Message;

class TransmitCreditNoteToPAMessage
{
    public function __construct(private int $creditNoteId) {}

    public function getCreditNoteId(): int
    {
        return $this->creditNoteId;
    }
}
