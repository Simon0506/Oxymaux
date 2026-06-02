<?php

namespace App\Entity;

use App\Repository\GoogleReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GoogleReviewRepository::class)]
class GoogleReview
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 191, unique: true)]
    private ?string $googleReviewId = null;

    #[ORM\Column(length: 255)]
    private ?string $authorName = null;

    #[ORM\Column]
    private ?int $rating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $relativeTimeDescription = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profilePhotoUrl = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishTime = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGoogleReviewId(): ?string
    {
        return $this->googleReviewId;
    }

    public function setGoogleReviewId(string $googleReviewId): static
    {
        $this->googleReviewId = $googleReviewId;

        return $this;
    }

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }

    public function setAuthorName(string $authorName): static
    {
        $this->authorName = $authorName;

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getRelativeTimeDescription(): ?string
    {
        return $this->relativeTimeDescription;
    }

    public function setRelativeTimeDescription(?string $relativeTimeDescription): static
    {
        $this->relativeTimeDescription = $relativeTimeDescription;

        return $this;
    }

    public function getProfilePhotoUrl(): ?string
    {
        return $this->profilePhotoUrl;
    }

    public function setProfilePhotoUrl(?string $profilePhotoUrl): static
    {
        $this->profilePhotoUrl = $profilePhotoUrl;

        return $this;
    }

    public function getPublishTime(): ?\DateTimeImmutable
    {
        return $this->publishTime;
    }

    public function setPublishTime(?\DateTimeImmutable $publishTime): static
    {
        $this->publishTime = $publishTime;

        return $this;
    }
}
