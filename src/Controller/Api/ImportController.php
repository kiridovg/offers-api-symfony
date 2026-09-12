<?php

namespace App\Controller\Api;

use App\Dto\Request\CreateImportInput;
use App\Dto\Response\ImportAcceptedView;
use App\Service\ImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class ImportController extends AbstractController
{
    public function __construct(private readonly ImportService $imports)
    {
    }

    #[Route('/api/imports', name: 'api_imports_store', methods: ['POST'])]
    public function store(#[MapRequestPayload] CreateImportInput $input): JsonResponse
    {
        $import = $this->imports->register($input);

        return $this->json(
            new ImportAcceptedView((int) $import->getId(), $import->getStatus()),
            Response::HTTP_ACCEPTED,
        );
    }
}
