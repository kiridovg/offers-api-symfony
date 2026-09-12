<?php

namespace App\Service;

use App\Entity\Reservation;

final readonly class ReservationOutcome
{
    public function __construct(
        public Reservation $reservation,
        public bool $created,
    ) {
    }
}
