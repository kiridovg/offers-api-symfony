<?php

namespace App\Dto\Response;

final readonly class PaginationLinks
{
    public function __construct(
        public ?string $prev,
        public ?string $next,
    ) {
    }
}
