<?php

namespace App\Dto\Response;

use App\Enum\ImportStatus;

final readonly class ImportAcceptedView
{
    public function __construct(
        public int $id,
        public ImportStatus $status,
    ) {
    }
}
