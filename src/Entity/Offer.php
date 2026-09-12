<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\OfferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OfferRepository::class)]
#[ORM\Table(name: 'offers')]
#[ORM\UniqueConstraint(name: 'uniq_offers_supplier_external_id', columns: ['supplier_id', 'external_id'])]
#[ORM\Index(name: 'idx_offers_search', columns: ['check_in', 'check_out', 'max_guests', 'price'])]
#[ORM\Index(name: 'idx_offers_property_price', columns: ['property_id', 'price'])]
#[ORM\HasLifecycleCallbacks]
class Offer
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Supplier::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Supplier $supplier;

    #[ORM\ManyToOne(targetEntity: Property::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Property $property;

    #[ORM\ManyToOne(targetEntity: Import::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Import $import;

    #[ORM\Column(length: 100)]
    private string $externalId;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $checkIn;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $checkOut;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $maxGuests;

    #[ORM\Column]
    private int $price;

    #[ORM\Column(length: 3, options: ['fixed' => true])]
    private string $currency;

    #[ORM\Column]
    private int $availableUnits;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    public function __construct(
        Supplier $supplier,
        Property $property,
        Import $import,
        string $externalId,
        \DateTimeImmutable $checkIn,
        \DateTimeImmutable $checkOut,
        int $maxGuests,
        int $price,
        string $currency,
        int $availableUnits,
        \DateTimeImmutable $expiresAt,
    ) {
        $this->supplier = $supplier;
        $this->property = $property;
        $this->import = $import;
        $this->externalId = $externalId;
        $this->checkIn = $checkIn;
        $this->checkOut = $checkOut;
        $this->maxGuests = $maxGuests;
        $this->price = $price;
        $this->currency = $currency;
        $this->availableUnits = $availableUnits;
        $this->expiresAt = $expiresAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSupplier(): Supplier
    {
        return $this->supplier;
    }

    public function getProperty(): Property
    {
        return $this->property;
    }

    public function getImport(): Import
    {
        return $this->import;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getCheckIn(): \DateTimeImmutable
    {
        return $this->checkIn;
    }

    public function getCheckOut(): \DateTimeImmutable
    {
        return $this->checkOut;
    }

    public function getMaxGuests(): int
    {
        return $this->maxGuests;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getAvailableUnits(): int
    {
        return $this->availableUnits;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function reserveUnit(): void
    {
        --$this->availableUnits;
    }
}
