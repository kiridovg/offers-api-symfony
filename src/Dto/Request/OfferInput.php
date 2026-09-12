<?php

namespace App\Dto\Request;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class OfferInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $externalId,

        #[Assert\Valid]
        public PropertyInput $property,

        #[Context([DateTimeNormalizer::FORMAT_KEY => '!Y-m-d'])]
        public \DateTimeImmutable $checkIn,

        #[Context([DateTimeNormalizer::FORMAT_KEY => '!Y-m-d'])]
        #[Assert\GreaterThan(propertyPath: 'checkIn')]
        public \DateTimeImmutable $checkOut,

        #[Assert\Range(min: 1, max: 50)]
        public int $maxGuests,

        #[Assert\PositiveOrZero]
        public int $price,

        #[Assert\Length(exactly: 3)]
        #[Assert\Regex('/^[A-Za-z]{3}$/')]
        public string $currency,

        #[Assert\Range(min: 0, max: 1000)]
        public int $availableUnits,

        public \DateTimeImmutable $expiresAt,
    ) {
    }

    public function normalizedCurrency(): string
    {
        return mb_strtoupper($this->currency);
    }
}
