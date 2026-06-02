<?php

namespace App\Entity;

use App\Repository\PriceKmRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PriceKmRepository::class)]
class PriceKm
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $minLength = null;

    #[ORM\Column]
    private ?int $maxLength = null;

    #[ORM\Column]
    private ?float $price = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMinLength(): ?int
    {
        return $this->minLength;
    }

    public function setMinLength(int $minLength): static
    {
        $this->minLength = $minLength;

        return $this;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function setMaxLength(int $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }
}
