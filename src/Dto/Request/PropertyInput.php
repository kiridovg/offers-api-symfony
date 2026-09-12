<?php

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PropertyInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $code,

        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,

        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $city,
    ) {
    }

    public function normalizedCode(): string
    {
        return mb_strtoupper($this->code);
    }
}
