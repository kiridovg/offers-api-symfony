<?php

namespace App\Dto\Request;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SearchPropertiesInput
{
    public const DEFAULT_PER_PAGE = 15;

    public function __construct(
        #[Context(denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => '!Y-m-d'])]
        public \DateTimeImmutable $checkIn,

        #[Context(denormalizationContext: [DateTimeNormalizer::FORMAT_KEY => '!Y-m-d'])]
        #[Assert\GreaterThan(propertyPath: 'checkIn')]
        public \DateTimeImmutable $checkOut,

        #[Assert\Positive]
        public int $guests,

        #[Assert\Length(max: 100)]
        public ?string $city = null,

        #[Assert\Positive]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 100)]
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
