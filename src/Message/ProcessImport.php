<?php

namespace App\Message;

final readonly class ProcessImport
{
    public function __construct(public int $importId)
    {
    }
}
