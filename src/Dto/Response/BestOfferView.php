<?php

namespace App\Dto\Response;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

final readonly class BestOfferView
{
    public function __construct(
        public int $id,
        public string $supplier,
        public int $price,
        public string $currency,
        public int $availableUnits,
        #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:s\Z'])]
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
