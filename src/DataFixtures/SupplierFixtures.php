<?php

namespace App\DataFixtures;

use App\Entity\Supplier;
use App\Repository\SupplierRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SupplierFixtures extends Fixture
{
    private const SUPPLIERS = [
        'supplier-a' => 'Supplier A',
        'supplier-b' => 'Supplier B',
    ];

    public function __construct(private readonly SupplierRepository $suppliers)
    {
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::SUPPLIERS as $code => $name) {
            $supplier = $this->suppliers->findOneBy(['code' => $code]);

            if (!$supplier instanceof Supplier) {
                $manager->persist(new Supplier($code, $name));

                continue;
            }

            $supplier->rename($name);
        }

        $manager->flush();
    }
}
