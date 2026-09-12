<?php

namespace App\Tests\Functional;

use App\Entity\Import;
use App\Enum\ImportStatus;
use App\Message\ProcessImport;
use App\Repository\ImportRepository;
use App\Tests\Factory\SupplierFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

final class ImportSubmissionTest extends WebTestCase
{
    use Factories;
    use InteractsWithMessenger;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
    }

    public function testItAcceptsImportAndQueuesProcessing(): void
    {
        SupplierFactory::createOne(['code' => 'supplier-a']);

        $this->post($this->payload());

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $body = $this->json();
        self::assertSame(ImportStatus::Pending->value, $body['status']);

        $import = $this->imports()->find($body['id']);
        self::assertInstanceOf(Import::class, $import);
        self::assertSame('imp-2026-0001', $import->getExternalImportId());
        self::assertSame(ImportStatus::Pending, $import->getStatus());
        self::assertSame(1, $import->getTotalOffers());

        $this->transport('async')->queue()->assertContains(ProcessImport::class, 1);

        $messages = $this->transport('async')->queue()->messages(ProcessImport::class);
        self::assertEquals(new ProcessImport($body['id']), $messages[0]);
    }

    public function testItDoesNotDuplicateImportOrRequeueProcessing(): void
    {
        SupplierFactory::createOne(['code' => 'supplier-a']);

        $this->post($this->payload());
        $first = $this->json();

        $this->post($this->payload());
        $second = $this->json();

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        self::assertSame($first['id'], $second['id']);
        self::assertCount(1, $this->imports()->findAll());
        $this->transport('async')->queue()->assertContains(ProcessImport::class, 1);
    }

    public function testItRejectsUnknownSupplier(): void
    {
        $this->post($this->payload(['supplier' => 'supplier-z']));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame('Unknown supplier.', $this->violationFor('supplier'));
        $this->transport('async')->queue()->assertEmpty();
    }

    public function testItRejectsDuplicatedOfferIdsWithinSinglePayload(): void
    {
        SupplierFactory::createOne(['code' => 'supplier-a']);

        $this->post($this->payload([
            'offers' => [
                $this->offer(),
                $this->offer(['price' => 50000]),
            ],
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertNotNull($this->violationFor('offers[1].external_id'));
        self::assertCount(0, $this->imports()->findAll());
    }

    public function testItRejectsCheckOutBeforeCheckIn(): void
    {
        SupplierFactory::createOne(['code' => 'supplier-a']);

        $this->post($this->payload([
            'offers' => [
                $this->offer(['check_in' => '2026-10-15', 'check_out' => '2026-10-10']),
            ],
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertNotNull($this->violationFor('offers[0].check_out'));
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
    private function post(array $payload): void
    {
        $this->client->request(
            'POST',
            '/api/imports',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        return $decoded;
    }

    private function violationFor(string $propertyPath): ?string
    {
        $body = $this->json();

        /** @var list<array{property_path: string, title: string}> $violations */
        $violations = $body['violations'] ?? [];

        foreach ($violations as $violation) {
            if ($violation['property_path'] === $propertyPath) {
                return $violation['title'];
            }
        }

        return null;
    }

    private function imports(): ImportRepository
    {
        $repository = self::getContainer()->get(ImportRepository::class);
        self::assertInstanceOf(ImportRepository::class, $repository);

        return $repository;
    }
}
