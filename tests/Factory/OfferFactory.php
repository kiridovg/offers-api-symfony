<?php

namespace App\Tests\Factory;

use App\Entity\Offer;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Offer>
 */
final class OfferFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Offer::class;
    }

    public function soldOut(): static
    {
        return $this->with(['availableUnits' => 0]);
    }

    public function expired(): static
    {
        return $this->with(['expiresAt' => new \DateTimeImmutable('-1 day')]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        $supplier = SupplierFactory::new();

        return [
            'supplier' => $supplier,
            'property' => PropertyFactory::new(),
            'import' => ImportFactory::new(['supplier' => $supplier]),
            'externalId' => self::faker()->unique()->bothify('offer-########'),
            'checkIn' => new \DateTimeImmutable('2026-10-10'),
            'checkOut' => new \DateTimeImmutable('2026-10-15'),
            'maxGuests' => 4,
            'price' => self::faker()->numberBetween(10_000, 200_000),
            'currency' => 'EUR',
            'availableUnits' => 2,
            'expiresAt' => new \DateTimeImmutable('+7 days'),
        ];
    }
}
