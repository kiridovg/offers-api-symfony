<?php

namespace App\Tests\Factory;

use App\Entity\Supplier;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Supplier>
 */
final class SupplierFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Supplier::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'code' => self::faker()->unique()->slug(2),
            'name' => self::faker()->company(),
        ];
    }
}
