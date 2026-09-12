<?php

namespace App\Service;

use App\Dto\Request\OfferInput;
use App\Entity\Import;
use App\Enum\ImportStatus;
use App\Repository\ImportRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

final class ImportProcessor
{
    private const CHUNK_SIZE = 500;
    private const DATE_FORMAT = 'Y-m-d';
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:sP';

    public function __construct(
        private readonly Connection $connection,
        private readonly ImportRepository $imports,
        private readonly DenormalizerInterface $denormalizer,
    ) {
    }

    public function process(int $importId): void
    {
        if (!$this->claim($importId)) {
            return;
        }

        $import = $this->imports->find($importId);

        if (!$import instanceof Import) {
            return;
        }

        $supplierId = $import->getSupplier()->getId();

        if (null === $supplierId) {
            throw new \LogicException('Import supplier has no identifier.');
        }

        $processed = 0;

        foreach (array_chunk($this->offers($import), self::CHUNK_SIZE) as $chunk) {
            $this->connection->transactional(
                function () use ($importId, $supplierId, $chunk, &$processed): void {
                    $propertyIds = $this->upsertProperties($chunk);
                    $this->upsertOffers($importId, $supplierId, $chunk, $propertyIds);

                    $processed += \count($chunk);

                    $this->connection->executeStatement(
                        'UPDATE imports SET processed_offers = ?, updated_at = ? WHERE id = ?',
                        [$processed, $this->now(), $importId],
                    );
                },
            );
        }

        $this->connection->executeStatement(
            'UPDATE imports SET status = ?, error = NULL, completed_at = ?, updated_at = ? WHERE id = ?',
            [ImportStatus::Completed->value, $this->now(), $this->now(), $importId],
        );
    }

    public function markFailed(int $importId, string $error): void
    {
        $this->connection->executeStatement(
            'UPDATE imports SET status = ?, error = ?, updated_at = ? WHERE id = ?',
            [ImportStatus::Failed->value, $error, $this->now(), $importId],
        );
    }

    private function claim(int $importId): bool
    {
        $claimed = $this->connection->executeStatement(
            'UPDATE imports SET status = ?, updated_at = ? WHERE id = ? AND status IN (?, ?)',
            [
                ImportStatus::Processing->value,
                $this->now(),
                $importId,
                ImportStatus::Pending->value,
                ImportStatus::Processing->value,
            ],
        );

        return $claimed > 0;
    }

    /**
     * @return list<OfferInput>
     */
    private function offers(Import $import): array
    {
        /** @var list<OfferInput> $offers */
        $offers = $this->denormalizer->denormalize($import->getPayload(), OfferInput::class.'[]');

        return $offers;
    }

    /**
     * @param list<OfferInput> $offers
     *
     * @return array<string, int>
     */
    private function upsertProperties(array $offers): array
    {
        $properties = [];

        foreach ($offers as $offer) {
            $properties[$offer->property->normalizedCode()] = $offer->property;
        }

        $now = $this->now();
        $placeholders = [];
        $parameters = [];

        foreach ($properties as $code => $property) {
            $placeholders[] = '(?, ?, ?, ?, ?)';
            array_push($parameters, $code, $property->name, $property->city, $now, $now);
        }

        $rows = $this->connection->fetchAllAssociative(
            'INSERT INTO properties (code, name, city, created_at, updated_at) VALUES '
            .implode(', ', $placeholders)
            .' ON CONFLICT (code) DO UPDATE SET name = EXCLUDED.name, city = EXCLUDED.city, updated_at = EXCLUDED.updated_at'
            .' RETURNING id, code',
            $parameters,
        );

        $ids = [];

        foreach ($rows as $row) {
            $ids[(string) $row['code']] = (int) $row['id'];
        }

        return $ids;
    }

    /**
     * @param list<OfferInput>   $offers
     * @param array<string, int> $propertyIds
     */
    private function upsertOffers(int $importId, int $supplierId, array $offers, array $propertyIds): void
    {
        $now = $this->now();
        $placeholders = [];
        $parameters = [];

        foreach ($offers as $offer) {
            $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

            array_push(
                $parameters,
                $supplierId,
                $propertyIds[$offer->property->normalizedCode()],
                $importId,
                $offer->externalId,
                $offer->checkIn->format(self::DATE_FORMAT),
                $offer->checkOut->format(self::DATE_FORMAT),
                $offer->maxGuests,
                $offer->price,
                $offer->normalizedCurrency(),
                $offer->availableUnits,
                $offer->expiresAt->setTimezone(new \DateTimeZone('UTC'))->format(self::TIMESTAMP_FORMAT),
                $now,
                $now,
            );
        }

        $this->connection->executeStatement(
            'INSERT INTO offers (supplier_id, property_id, import_id, external_id, check_in, check_out,'
            .' max_guests, price, currency, available_units, expires_at, created_at, updated_at) VALUES '
            .implode(', ', $placeholders)
            .' ON CONFLICT (supplier_id, external_id) DO UPDATE SET'
            .' property_id = EXCLUDED.property_id,'
            .' import_id = EXCLUDED.import_id,'
            .' check_in = EXCLUDED.check_in,'
            .' check_out = EXCLUDED.check_out,'
            .' max_guests = EXCLUDED.max_guests,'
            .' price = EXCLUDED.price,'
            .' currency = EXCLUDED.currency,'
            .' available_units = EXCLUDED.available_units,'
            .' expires_at = EXCLUDED.expires_at,'
            .' updated_at = EXCLUDED.updated_at',
            $parameters,
        );
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(self::TIMESTAMP_FORMAT);
    }
}
