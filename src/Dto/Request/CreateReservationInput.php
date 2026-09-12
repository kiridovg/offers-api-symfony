<?php

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateReservationInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $clientReference,

        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $customerName,

        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 255)]
        public string $customerEmail,
    ) {
    }
}
