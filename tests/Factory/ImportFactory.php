<?php

namespace App\Tests\Factory;

use App\Entity\Import;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Import>
 */
final class ImportFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Import::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'supplier' => SupplierFactory::new(),
            'externalImportId' => self::faker()->unique()->bothify('imp-####'),
            'sentAt' => new \DateTimeImmutable('-1 hour'),
            'payload' => [],
        ];
    }
}
