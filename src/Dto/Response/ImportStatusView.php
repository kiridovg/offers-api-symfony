<?php

namespace App\Dto\Response;

use App\Entity\Import;
use App\Enum\ImportStatus;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

final readonly class ImportStatusView
{
    private const ZULU = 'Y-m-d\TH:i:s\Z';

    public function __construct(
        public int $id,
        public string $supplier,
        public string $externalImportId,
        #[Context([DateTimeNormalizer::FORMAT_KEY => self::ZULU])]
        public \DateTimeImmutable $sentAt,
        public ImportStatus $status,
        public int $totalOffers,
        public int $processedOffers,
        public ?string $error,
        #[Context([DateTimeNormalizer::FORMAT_KEY => self::ZULU])]
        public \DateTimeImmutable $createdAt,
        #[Context([DateTimeNormalizer::FORMAT_KEY => self::ZULU])]
        public ?\DateTimeImmutable $completedAt,
    ) {
    }

    public static function fromEntity(Import $import): self
    {
        $completedAt = $import->getCompletedAt();

        return new self(
            (int) $import->getId(),
            $import->getSupplier()->getCode(),
            $import->getExternalImportId(),
            self::utc($import->getSentAt()),
            $import->getStatus(),
            $import->getTotalOffers(),
            $import->getProcessedOffers(),
            $import->getError(),
            self::utc($import->getCreatedAt()),
            null === $completedAt ? null : self::utc($completedAt),
        );
    }

    private static function utc(\DateTimeImmutable $moment): \DateTimeImmutable
    {
        return $moment->setTimezone(new \DateTimeZone('UTC'));
    }
}
