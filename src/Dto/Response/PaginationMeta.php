<?php

namespace App\Dto\Response;

final readonly class PaginationMeta
{
    public function __construct(
        public int $page,
        public int $perPage,
        public int $count,
    ) {
    }
}
