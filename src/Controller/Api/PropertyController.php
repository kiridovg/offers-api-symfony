<?php

namespace App\Controller\Api;

use App\Dto\Request\SearchPropertiesInput;
use App\Service\PropertySearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

class PropertyController extends AbstractController
{
    public function __construct(private readonly PropertySearchService $search)
    {
    }

    #[Route('/api/properties', name: 'api_properties_index', methods: ['GET'])]
    public function index(#[MapQueryString] SearchPropertiesInput $input): JsonResponse
    {
        return $this->json($this->search->search($input));
    }
}
