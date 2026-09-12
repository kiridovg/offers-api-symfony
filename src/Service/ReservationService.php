<?php

namespace App\Service;

use App\Dto\Request\CreateReservationInput;
use App\Entity\Offer;
use App\Entity\Reservation;
use App\Exception\OfferUnavailableException;
use App\Repository\ReservationRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class ReservationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationRepository $reservations,
    ) {
    }

    public function reserve(Offer $offer, CreateReservationInput $input): ReservationOutcome
    {
        return $this->entityManager->wrapInTransaction(
            function (EntityManagerInterface $entityManager) use ($offer, $input): ReservationOutcome {
                $entityManager->refresh($offer, LockMode::PESSIMISTIC_WRITE);

                $existing = $this->reservations->findOneBy([
                    'offer' => $offer->getId(),
                    'clientReference' => $input->clientReference,
                ]);

                if ($existing instanceof Reservation) {
                    return new ReservationOutcome($existing, false);
                }

                if ($offer->getExpiresAt() <= new \DateTimeImmutable()) {
                    throw OfferUnavailableException::expired();
                }

                if ($offer->getAvailableUnits() < 1) {
                    throw OfferUnavailableException::soldOut();
                }

                $offer->reserveUnit();

                $reservation = new Reservation(
                    $offer,
                    $input->clientReference,
                    $input->customerName,
                    $input->customerEmail,
                );

                $entityManager->persist($reservation);

                return new ReservationOutcome($reservation, true);
            },
        );
    }
}
