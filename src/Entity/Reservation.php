<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\ReservationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservations')]
#[ORM\UniqueConstraint(name: 'uniq_reservations_offer_client_reference', columns: ['offer_id', 'client_reference'])]
#[ORM\HasLifecycleCallbacks]
class Reservation
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Offer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Offer $offer;

    #[ORM\Column(length: 100)]
    private string $clientReference;

    #[ORM\Column(length: 255)]
    private string $customerName;

    #[ORM\Column(length: 255)]
    private string $customerEmail;

    public function __construct(
        Offer $offer,
        string $clientReference,
        string $customerName,
        string $customerEmail,
    ) {
        $this->offer = $offer;
        $this->clientReference = $clientReference;
        $this->customerName = $customerName;
        $this->customerEmail = $customerEmail;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOffer(): Offer
    {
        return $this->offer;
    }

    public function getClientReference(): string
    {
        return $this->clientReference;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }
}
