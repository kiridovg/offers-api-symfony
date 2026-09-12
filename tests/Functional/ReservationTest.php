<?php

namespace App\Tests\Functional;

use App\Entity\Offer;
use App\Repository\OfferRepository;
use App\Repository\ReservationRepository;
use App\Tests\Factory\OfferFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;

final class ReservationTest extends WebTestCase
{
    use Factories;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
    }

    public function testItCreatesReservationAndDecrementsAvailableUnits(): void
    {
        $offerId = $this->offer(['availableUnits' => 2]);

        $this->book($offerId);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $body = $this->json();
        self::assertSame($offerId, $body['offer_id']);
        self::assertSame('web-order-9f782b1c', $body['client_reference']);

        self::assertSame(1, $this->availableUnits($offerId));
        self::assertCount(1, $this->reservations()->findAll());
    }

    public function testItRejectsReservationWhenNoUnitsLeft(): void
    {
        $offerId = $this->offer(['availableUnits' => 0]);

        $this->book($offerId);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('Offer has no available units left.', $this->json()['detail']);
        self::assertCount(0, $this->reservations()->findAll());
    }

    public function testItRejectsReservationForExpiredOffer(): void
    {
        $offerId = $this->offer(['expiresAt' => new \DateTimeImmutable('-1 day')]);

        $this->book($offerId);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('Offer has expired.', $this->json()['detail']);
        self::assertCount(0, $this->reservations()->findAll());
    }

    public function testItDoesNotBookTwiceForTheSameClientReference(): void
    {
        $offerId = $this->offer(['availableUnits' => 2]);

        $this->book($offerId);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $first = $this->json();

        $this->book($offerId);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $second = $this->json();

        self::assertSame($first['id'], $second['id']);
        self::assertSame(1, $this->availableUnits($offerId));
        self::assertCount(1, $this->reservations()->findAll());
    }

    public function testItDoesNotReturnReservationMadeForAnotherOffer(): void
    {
        $first = $this->offer(['availableUnits' => 2]);
        $second = $this->offer(['availableUnits' => 2]);

        $this->book($first);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $firstBody = $this->json();

        $this->book($second, ['customer_name' => 'Jane Doe', 'customer_email' => 'jane@example.com']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $secondBody = $this->json();

        self::assertNotSame($firstBody['id'], $secondBody['id']);
        self::assertSame($second, $secondBody['offer_id']);
        self::assertCount(2, $this->reservations()->findAll());
    }

    public function testItBooksTheLastUnitOnlyOnce(): void
    {
        $offerId = $this->offer(['availableUnits' => 1]);

        $this->book($offerId);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->book($offerId, ['client_reference' => 'web-order-second']);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        self::assertSame(0, $this->availableUnits($offerId));
        self::assertCount(1, $this->reservations()->findAll());
    }

    public function testItReturnsNotFoundForUnknownOffer(): void
    {
        $this->book(999999);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItValidatesReservationPayload(): void
    {
        $offerId = $this->offer();

        $this->client->request(
            'POST',
            '/api/offers/'.$offerId.'/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['customer_email' => 'not-an-email'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $paths = array_column($this->json()['violations'], 'property_path');

        self::assertContains('client_reference', $paths);
        self::assertContains('customer_name', $paths);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function offer(array $attributes = []): int
    {
        return (int) OfferFactory::createOne($attributes)->getId();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function book(int $offerId, array $overrides = []): void
    {
        $this->client->request(
            'POST',
            '/api/offers/'.$offerId.'/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(array_replace([
                'client_reference' => 'web-order-9f782b1c',
                'customer_name' => 'John Doe',
                'customer_email' => 'john@example.com',
            ], $overrides), \JSON_THROW_ON_ERROR),
        );
    }

    private function availableUnits(int $offerId): int
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->clear();

        $repository = self::getContainer()->get(OfferRepository::class);
        self::assertInstanceOf(OfferRepository::class, $repository);

        $offer = $repository->find($offerId);
        self::assertInstanceOf(Offer::class, $offer);

        return $offer->getAvailableUnits();
    }

    private function reservations(): ReservationRepository
    {
        $repository = self::getContainer()->get(ReservationRepository::class);
        self::assertInstanceOf(ReservationRepository::class, $repository);

        return $repository;
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
