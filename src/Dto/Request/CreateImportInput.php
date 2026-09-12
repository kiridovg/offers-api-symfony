<?php

namespace App\Dto\Request;

use App\Validator\SupplierExists;
use App\Validator\UniqueOfferIds;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateImportInput
{
    /**
     * @param list<OfferInput> $offers
     */
    public function __construct(
        #[Assert\NotBlank]
        #[SupplierExists]
        public string $supplier,

        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $externalImportId,

        public \DateTimeImmutable $sentAt,

        #[Assert\Count(min: 1, max: 1000)]
        #[Assert\Valid]
        #[UniqueOfferIds]
        public array $offers,
    ) {
    }
}
