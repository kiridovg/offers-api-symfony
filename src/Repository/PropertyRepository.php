<?php

namespace App\Repository;

use App\Entity\Property;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Property>
 */
class PropertyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Property::class);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findWithCheapestOffer(
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
        int $guests,
        ?string $city,
        int $limit,
        int $offset,
    ): array {
        $cityFilter = null === $city
            ? ''
            : ' AND EXISTS (SELECT 1 FROM properties pc WHERE pc.id = o.property_id AND pc.city = :city)';

        $sql = <<<SQL
            SELECT
                p.code,
                p.name,
                p.city,
                b.id AS best_offer_id,
                b.price AS best_offer_price,
                b.currency AS best_offer_currency,
                b.available_units AS best_offer_available_units,
                b.expires_at AS best_offer_expires_at,
                s.code AS best_offer_supplier
            FROM properties p
            INNER JOIN (
                SELECT
                    o.id,
                    o.property_id,
                    o.supplier_id,
                    o.price,
                    o.currency,
                    o.available_units,
                    o.expires_at,
                    ROW_NUMBER() OVER (PARTITION BY o.property_id ORDER BY o.price, o.id) AS rn
                FROM offers o
                WHERE o.check_in = :check_in
                  AND o.check_out = :check_out
                  AND o.max_guests >= :guests
                  AND o.available_units > 0
                  AND o.expires_at > :now{$cityFilter}
            ) b ON b.property_id = p.id AND b.rn = 1
            INNER JOIN suppliers s ON s.id = b.supplier_id
            ORDER BY b.price, p.id
            LIMIT :limit OFFSET :offset
            SQL;

        $parameters = [
            'check_in' => $checkIn->format('Y-m-d'),
            'check_out' => $checkOut->format('Y-m-d'),
            'guests' => $guests,
            'now' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:sP'),
            'limit' => $limit,
            'offset' => $offset,
        ];

        if (null !== $city) {
            $parameters['city'] = $city;
        }

        return $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative($sql, $parameters);
    }
}
