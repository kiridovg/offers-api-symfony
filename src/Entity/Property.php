<?php

namespace App\Entity;

use App\Entity\Trait\TimestampableTrait;
use App\Repository\PropertyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PropertyRepository::class)]
#[ORM\Table(name: 'properties')]
#[ORM\UniqueConstraint(name: 'uniq_properties_code', columns: ['code'])]
#[ORM\Index(name: 'idx_properties_city', columns: ['city'])]
#[ORM\HasLifecycleCallbacks]
class Property
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 100)]
    private string $city;

    public function __construct(string $code, string $name, string $city)
    {
        $this->code = $code;
        $this->name = $name;
        $this->city = $city;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCity(): string
    {
        return $this->city;
    }
}
