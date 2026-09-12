<?php

namespace App\Tests\Factory;

use App\Entity\Property;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Property>
 */
final class PropertyFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Property::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'code' => mb_strtoupper(self::faker()->unique()->bothify('???-####')),
            'name' => self::faker()->streetName().' Apartment',
            'city' => self::faker()->city(),
        ];
    }
}
