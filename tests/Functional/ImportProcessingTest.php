<?php

namespace App\Tests\Functional;

use App\Enum\ImportStatus;
use App\EventListener\ImportFailureListener;
use App\Message\ProcessImport;
use App\Service\ImportProcessor;
use App\Tests\Factory\SupplierFactory;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Zenstruck\Foundry\Test\Factories;

final class ImportProcessingTest extends WebTestCase
{
    use Factories;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        SupplierFactory::createOne(['code' => 'supplier-a']);
        SupplierFactory::createOne(['code' => 'supplier-b']);
    }

    public function testItCreatesPropertiesAndOffers(): void
    {
        $importId = $this->submit($this->payload());

        $this->processor()->process($importId);

        $import = $this->importRow($importId);
        self::assertSame(ImportStatus::Completed->value, $import['status']);
        self::assertSame(1, (int) $import['total_offers']);
        self::assertSame(1, (int) $import['processed_offers']);
        self::assertNotNull($import['completed_at']);
        self::assertNull($import['error']);

        $property = $this->row('SELECT * FROM properties');
        self::assertSame('BCN-0001', $property['code']);
        self::assertSame('Barcelona', $property['city']);

        $offer = $this->row('SELECT * FROM offers');
        self::assertSame('offer-1', $offer['external_id']);
        self::assertSame(72500, (int) $offer['price']);
        self::assertSame('EUR', $offer['currency']);
        self::assertSame('2026-10-10', $offer['check_in']);
        self::assertSame((int) $property['id'], (int) $offer['property_id']);
    }

    public function testItDoesNotProcessTheSameImportTwice(): void
    {
        $importId = $this->submit($this->payload());

        $this->processor()->process($importId);
        $completedAt = $this->importRow($importId)['completed_at'];

        $this->processor()->process($importId);

        self::assertSame($completedAt, $this->importRow($importId)['completed_at']);
        self::assertSame(1, $this->countRows('offers'));
    }

    public function testItUpdatesExistingOfferReceivedInAnotherImport(): void
    {
        $this->processor()->process($this->submit($this->payload()));

        $second = $this->submit($this->payload([
            'external_import_id' => 'imp-2026-0002',
            'offers' => [$this->offer(['price' => 50000, 'available_units' => 1])],
        ]));

        $this->processor()->process($second);

        self::assertSame(1, $this->countRows('offers'));
        self::assertSame(1, $this->countRows('properties'));

        $offer = $this->row('SELECT * FROM offers');
        self::assertSame(50000, (int) $offer['price']);
        self::assertSame(1, (int) $offer['available_units']);
        self::assertSame($second, (int) $offer['import_id']);
    }

    public function testItReusesPropertyAcrossSuppliers(): void
    {
        $this->processor()->process($this->submit($this->payload()));

        $this->processor()->process($this->submit($this->payload([
            'supplier' => 'supplier-b',
            'external_import_id' => 'imp-b-0001',
        ])));

        self::assertSame(1, $this->countRows('properties'));
        self::assertSame(2, $this->countRows('offers'));
    }

    public function testItTreatsPropertyCodeCaseInsensitively(): void
    {
        $this->processor()->process($this->submit($this->payload()));

        $this->processor()->process($this->submit($this->payload([
            'external_import_id' => 'imp-2026-0002',
            'offers' => [$this->offer([
                'external_id' => 'offer-2',
                'property' => [
                    'code' => 'bcn-0001',
                    'name' => 'Sea View Apartment',
                    'city' => 'Barcelona',
                ],
            ])],
        ])));

        self::assertSame(1, $this->countRows('properties'));
        self::assertSame('BCN-0001', $this->row('SELECT * FROM properties')['code']);
    }

    public function testItNormalizesTimezoneOffsetsToUtc(): void
    {
        $importId = $this->submit($this->payload([
            'sent_at' => '2026-09-01T13:00:00+03:00',
            'offers' => [$this->offer(['expires_at' => '2026-09-20T15:00:00+03:00'])],
        ]));

        $this->processor()->process($importId);

        self::assertStringStartsWith('2026-09-01 10:00:00', $this->importRow($importId)['sent_at']);
        self::assertStringStartsWith('2026-09-20 12:00:00', $this->row('SELECT * FROM offers')['expires_at']);
    }

    public function testItMarksImportAsFailedWhenProcessingThrows(): void
    {
        $importId = $this->submit($this->payload());

        $this->connection()->executeStatement(
            "UPDATE imports SET payload = replace(payload::text, '72500', '9999999999')::jsonb WHERE id = ?",
            [$importId],
        );

        $this->entityManager()->clear();

        $failure = null;

        try {
            $this->processor()->process($importId);
        } catch (\Throwable $throwable) {
            $failure = $throwable;
        }

        self::assertInstanceOf(\Throwable::class, $failure);

        $listener = self::getContainer()->get(ImportFailureListener::class);
        self::assertInstanceOf(ImportFailureListener::class, $listener);

        $listener(new WorkerMessageFailedEvent(
            new Envelope(new ProcessImport($importId)),
            'async',
            $failure,
        ));

        $import = $this->importRow($importId);
        self::assertSame(ImportStatus::Failed->value, $import['status']);
        self::assertNotNull($import['error']);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'supplier' => 'supplier-a',
            'external_import_id' => 'imp-2026-0001',
            'sent_at' => '2026-09-01T10:00:00+00:00',
            'offers' => [$this->offer()],
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function offer(array $overrides = []): array
    {
        return array_replace([
            'external_id' => 'offer-1',
            'property' => [
                'code' => 'BCN-0001',
                'name' => 'Sea View Apartment',
                'city' => 'Barcelona',
            ],
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'price' => 72500,
            'currency' => 'EUR',
            'available_units' => 3,
            'expires_at' => '2026-09-20T12:00:00+00:00',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function submit(array $payload): int
    {
        $this->client->request(
            'POST',
            '/api/imports',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        /** @var array{id: int} $body */
        $body = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        return $body['id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function importRow(int $importId): array
    {
        return $this->row('SELECT * FROM imports WHERE id = '.$importId);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $sql): array
    {
        $row = $this->connection()->fetchAssociative($sql);
        self::assertIsArray($row);

        return $row;
    }

    private function countRows(string $table): int
    {
        return (int) $this->connection()->fetchOne('SELECT count(*) FROM '.$table);
    }

    private function processor(): ImportProcessor
    {
        $processor = self::getContainer()->get(ImportProcessor::class);
        self::assertInstanceOf(ImportProcessor::class, $processor);

        return $processor;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
