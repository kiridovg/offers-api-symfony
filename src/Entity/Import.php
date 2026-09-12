<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Enum\ImportStatus;
use App\Repository\ImportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportRepository::class)]
#[ORM\Table(name: 'imports')]
#[ORM\UniqueConstraint(name: 'uniq_imports_supplier_external_id', columns: ['supplier_id', 'external_import_id'])]
#[ORM\HasLifecycleCallbacks]
class Import
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Supplier $supplier;

    #[ORM\Column(length: 100)]
    private string $externalImportId;

    #[ORM\Column(length: 20, enumType: ImportStatus::class)]
    private ImportStatus $status = ImportStatus::Pending;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $sentAt;

    /**
     * @var list<array<string, mixed>>
     */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $payload;

    #[ORM\Column]
    private int $totalOffers = 0;

    #[ORM\Column]
    private int $processedOffers = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    /**
     * @param list<array<string, mixed>> $payload
     */
    public function __construct(
        Supplier $supplier,
        string $externalImportId,
        \DateTimeImmutable $sentAt,
        array $payload,
    ) {
        $this->supplier = $supplier;
        $this->externalImportId = $externalImportId;
        $this->sentAt = $sentAt;
        $this->payload = $payload;
        $this->totalOffers = \count($payload);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSupplier(): Supplier
    {
        return $this->supplier;
    }

    public function getExternalImportId(): string
    {
        return $this->externalImportId;
    }

    public function getStatus(): ImportStatus
    {
        return $this->status;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getTotalOffers(): int
    {
        return $this->totalOffers;
    }

    public function getProcessedOffers(): int
    {
        return $this->processedOffers;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }
}
