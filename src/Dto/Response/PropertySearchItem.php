<?php

namespace App\Dto\Response;

final readonly class PropertySearchItem
{
    public function __construct(
        public string $code,
        public string $name,
        public string $city,
        public BestOfferView $bestOffer,
    ) {
    }
}
