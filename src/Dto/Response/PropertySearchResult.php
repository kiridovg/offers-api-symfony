<?php

namespace App\Dto\Response;

final readonly class PropertySearchResult
{
    /**
     * @param list<PropertySearchItem> $data
     */
    public function __construct(
        public array $data,
        public PaginationLinks $links,
        public PaginationMeta $meta,
    ) {
    }
}
