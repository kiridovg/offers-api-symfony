<?php

namespace App\Service;

use App\Dto\Request\CreateImportInput;
use App\Entity\Import;
use App\Entity\Supplier;
use App\Message\ProcessImport;
use App\Repository\ImportRepository;
use App\Repository\SupplierRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ImportService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ManagerRegistry $registry,
        private readonly ImportRepository $imports,
        private readonly SupplierRepository $suppliers,
        private readonly NormalizerInterface $normalizer,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function register(CreateImportInput $input): Import
    {
        $supplier = $this->suppliers->findOneBy(['code' => $input->supplier]);

        if (!$supplier instanceof Supplier) {
            throw new UnprocessableEntityHttpException('Unknown supplier.');
        }

        $supplierId = $supplier->getId();

        $existing = $this->imports->findOneBy([
            'supplier' => $supplierId,
            'externalImportId' => $input->externalImportId,
        ]);

        if ($existing instanceof Import) {
            return $existing;
        }

        $import = new Import(
            $supplier,
            $input->externalImportId,
            $input->sentAt->setTimezone(new \DateTimeZone('UTC')),
            $this->payload($input),
        );

        try {
            $this->entityManager->persist($import);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            return $this->recoverFromConcurrentRegistration($supplierId, $input->externalImportId, $exception);
        }

        $this->bus->dispatch(new ProcessImport($this->identify($import)));

        return $import;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function payload(CreateImportInput $input): array
    {
        /** @var list<array<string, mixed>> $offers */
        $offers = $this->normalizer->normalize($input->offers);

        return $offers;
    }

    private function recoverFromConcurrentRegistration(
        ?int $supplierId,
        string $externalImportId,
        UniqueConstraintViolationException $exception,
    ): Import {
        $this->registry->resetManager();

        $existing = $this->registry->getRepository(Import::class)->findOneBy([
            'supplier' => $supplierId,
            'externalImportId' => $externalImportId,
        ]);

        if (!$existing instanceof Import) {
            throw $exception;
        }

        return $existing;
    }

    private function identify(Import $import): int
    {
        $id = $import->getId();

        if (null === $id) {
            throw new \LogicException('Import has no identifier after flush.');
        }

        return $id;
    }
}
