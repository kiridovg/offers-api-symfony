<?php

namespace App\Controller\Api;

use App\Dto\Request\CreateReservationInput;
use App\Dto\Response\ReservationView;
use App\Entity\Offer;
use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class ReservationController extends AbstractController
{
    public function __construct(private readonly ReservationService $reservations)
    {
    }

    #[Route(
        '/api/offers/{id}/reservations',
        name: 'api_offers_reservations_store',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function store(Offer $offer, #[MapRequestPayload] CreateReservationInput $input): JsonResponse
    {
        $outcome = $this->reservations->reserve($offer, $input);

        return $this->json(
            ReservationView::fromEntity($outcome->reservation),
            $outcome->created ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }
}
