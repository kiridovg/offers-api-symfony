<?php

namespace App\Tests\Functional;

use App\Enum\ImportStatus;
use App\Service\ImportProcessor;
use App\Tests\Factory\SupplierFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;

final class ImportStatusTest extends WebTestCase
{
    use Factories;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        SupplierFactory::createOne(['code' => 'supplier-a']);
    }

    public function testItReturnsCurrentStateOfTheImport(): void
    {
        $importId = $this->submit();

        $this->client->request('GET', '/api/imports/'.$importId);

        self::assertResponseIsSuccessful();

        $body = $this->json();
        self::assertSame($importId, $body['id']);
        self::assertSame('supplier-a', $body['supplier']);
        self::assertSame('imp-2026-0001', $body['external_import_id']);
        self::assertSame('2026-09-01T10:00:00Z', $body['sent_at']);
        self::assertSame(ImportStatus::Pending->value, $body['status']);
        self::assertSame(1, $body['total_offers']);
        self::assertSame(0, $body['processed_offers']);
        self::assertNull($body['error']);
        self::assertNull($body['completed_at']);
        self::assertArrayHasKey('created_at', $body);
    }

    public function testItReportsProgressOfACompletedImport(): void
    {
        $importId = $this->submit();

        $processor = self::getContainer()->get(ImportProcessor::class);
        self::assertInstanceOf(ImportProcessor::class, $processor);
        $processor->process($importId);

        $this->client->request('GET', '/api/imports/'.$importId);

        $body = $this->json();
        self::assertSame(ImportStatus::Completed->value, $body['status']);
        self::assertSame(1, $body['processed_offers']);
        self::assertNotNull($body['completed_at']);
    }

    public function testItExposesTheErrorOfAFailedImport(): void
    {
        $importId = $this->submit();

        $processor = self::getContainer()->get(ImportProcessor::class);
        self::assertInstanceOf(ImportProcessor::class, $processor);
        $processor->markFailed($importId, 'Supplier feed was unreadable.');

        $this->client->request('GET', '/api/imports/'.$importId);

        $body = $this->json();
        self::assertSame(ImportStatus::Failed->value, $body['status']);
        self::assertSame('Supplier feed was unreadable.', $body['error']);
    }

    public function testItReturnsNotFoundForUnknownImport(): void
    {
        $this->client->request('GET', '/api/imports/999999');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame('application/problem+json', $this->client->getResponse()->headers->get('Content-Type'));
        self::assertSame('Resource not found.', $this->json()['detail']);
    }

    private function submit(): int
    {
        $this->client->request(
            'POST',
            '/api/imports',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'supplier' => 'supplier-a',
                'external_import_id' => 'imp-2026-0001',
                'sent_at' => '2026-09-01T10:00:00+00:00',
                'offers' => [[
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
                ]],
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        /** @var array{id: int} $body */
        $body = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body['id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
