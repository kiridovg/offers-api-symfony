<?php

namespace App\Tests\Functional;

use App\Tests\Factory\OfferFactory;
use App\Tests\Factory\PropertyFactory;
use App\Tests\Factory\SupplierFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;

final class PropertySearchTest extends WebTestCase
{
    use Factories;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
    }

    public function testItReturnsOnlyTheCheapestOfferForAProperty(): void
    {
        $property = PropertyFactory::createOne(['code' => 'BCN-0001', 'city' => 'Barcelona']);
        $supplier = SupplierFactory::createOne(['code' => 'supplier-b']);

        OfferFactory::createOne(['property' => $property, 'price' => 90000]);
        OfferFactory::createOne(['property' => $property, 'price' => 61000, 'supplier' => $supplier]);

        $body = $this->search();

        self::assertCount(1, $body['data']);
        self::assertSame('BCN-0001', $body['data'][0]['code']);
        self::assertSame(61000, $body['data'][0]['best_offer']['price']);
        self::assertSame('supplier-b', $body['data'][0]['best_offer']['supplier']);
        self::assertSame('EUR', $body['data'][0]['best_offer']['currency']);
    }

    public function testItExcludesSoldOutOffers(): void
    {
        OfferFactory::new()->soldOut()->create();

        self::assertCount(0, $this->search()['data']);
    }

    public function testItExcludesExpiredOffers(): void
    {
        OfferFactory::new()->expired()->create();

        self::assertCount(0, $this->search()['data']);
    }

    public function testItExcludesOffersThatExpiredInAnotherTimezone(): void
    {
        OfferFactory::createOne([
            'expiresAt' => new \DateTimeImmutable('-30 minutes', new \DateTimeZone('+05:00')),
        ]);

        self::assertCount(0, $this->search()['data']);
    }

    public function testItExcludesOffersWithNotEnoughCapacity(): void
    {
        OfferFactory::createOne(['maxGuests' => 2]);

        self::assertCount(0, $this->search(['guests' => 4])['data']);
        self::assertCount(1, $this->search(['guests' => 2])['data']);
    }

    public function testItExcludesOffersForOtherDates(): void
    {
        OfferFactory::createOne([
            'checkIn' => new \DateTimeImmutable('2026-11-01'),
            'checkOut' => new \DateTimeImmutable('2026-11-05'),
        ]);

        self::assertCount(0, $this->search()['data']);
    }

    public function testItFiltersByCity(): void
    {
        OfferFactory::createOne(['property' => PropertyFactory::new(['city' => 'Barcelona'])]);
        OfferFactory::createOne(['property' => PropertyFactory::new(['city' => 'Madrid'])]);

        $body = $this->search(['city' => 'Madrid']);

        self::assertCount(1, $body['data']);
        self::assertSame('Madrid', $body['data'][0]['city']);
    }

    public function testItOrdersPropertiesByBestOfferPrice(): void
    {
        OfferFactory::createOne(['property' => PropertyFactory::new(['code' => 'EXPENSIVE']), 'price' => 120000]);
        OfferFactory::createOne(['property' => PropertyFactory::new(['code' => 'CHEAP']), 'price' => 40000]);
        OfferFactory::createOne(['property' => PropertyFactory::new(['code' => 'MEDIUM']), 'price' => 80000]);

        $codes = array_column($this->search()['data'], 'code');

        self::assertSame(['CHEAP', 'MEDIUM', 'EXPENSIVE'], $codes);
    }

    public function testItPaginatesAndKeepsFiltersInLinks(): void
    {
        foreach ([40000, 80000, 120000] as $price) {
            OfferFactory::createOne([
                'property' => PropertyFactory::new(['city' => 'Barcelona']),
                'price' => $price,
            ]);
        }

        $first = $this->search(['city' => 'Barcelona', 'per_page' => 2]);

        self::assertCount(2, $first['data']);
        self::assertSame(1, $first['meta']['page']);
        self::assertSame(2, $first['meta']['per_page']);
        self::assertNull($first['links']['prev']);
        self::assertIsString($first['links']['next']);
        self::assertStringContainsString('city=Barcelona', $first['links']['next']);
        self::assertStringContainsString('page=2', $first['links']['next']);

        $second = $this->search(['city' => 'Barcelona', 'per_page' => 2, 'page' => 2]);

        self::assertCount(1, $second['data']);
        self::assertIsString($second['links']['prev']);
        self::assertStringContainsString('page=1', $second['links']['prev']);
        self::assertNull($second['links']['next']);
    }

    public function testItValidatesSearchParameters(): void
    {
        $this->client->request('GET', '/api/properties?check_in=2026-10-15&check_out=2026-10-10');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertContains('guests', array_column($this->json()['violations'], 'property_path'));

        $this->client->request('GET', '/api/properties?check_in=2026-10-15&check_out=2026-10-10&guests=2');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertContains('check_out', array_column($this->json()['violations'], 'property_path'));
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    private function search(array $filters = []): array
    {
        $query = array_replace([
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'guests' => 2,
        ], $filters);

        $this->client->request('GET', '/api/properties?'.http_build_query($query));

        self::assertResponseIsSuccessful();

        return $this->json();
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
