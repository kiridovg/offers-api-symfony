<?php

namespace App\Dto\Response;

use App\Entity\Reservation;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

final readonly class ReservationView
{
    public function __construct(
        public int $id,
        public int $offerId,
        public string $clientReference,
        public string $customerName,
        public string $customerEmail,
        #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:s\Z'])]
        public \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromEntity(Reservation $reservation): self
    {
        return new self(
            (int) $reservation->getId(),
            (int) $reservation->getOffer()->getId(),
            $reservation->getClientReference(),
            $reservation->getCustomerName(),
            $reservation->getCustomerEmail(),
            $reservation->getCreatedAt()->setTimezone(new \DateTimeZone('UTC')),
        );
    }
}
