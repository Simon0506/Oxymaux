<?php

namespace App\Entity;

use App\Repository\ActivityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityRepository::class)]
class Activity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'activities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Service $service = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $start = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $end = null;

    #[ORM\Column(nullable: true)]
    private ?int $nbPlaces = null;

    #[ORM\Column]
    private ?bool $openToAll = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    /**
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'activity', orphanRemoval: true)]
    private Collection $reservations;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleEventId = null;

    #[ORM\Column]
    private ?bool $googleNeedSync = true;

    #[ORM\Column(nullable: true)]
    private ?bool $canceled = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reasonCancel = null;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getStart(): ?\DateTime
    {
        return $this->start;
    }

    public function setStart(?\DateTime $start): static
    {
        $this->start = $start;

        return $this;
    }

    public function getEnd(): ?\DateTime
    {
        return $this->end;
    }

    public function setEnd(?\DateTime $end): static
    {
        $this->end = $end;

        return $this;
    }

    public function getFullDate(): ?string
    {
        if (!$this->date) {
            return null;
        }
        if ($this->start && $this->end) {
            return $this->date->format('d/m/Y') . ' de ' . $this->start->format('H\hi') . ' à ' . $this->end->format('H\hi');
        } else if ($this->start) {
            return $this->date->format('d/m/Y') . ' à partir de ' . $this->start->format('H\hi');
        } else if ($this->end) {
            return $this->date->format('d/m/Y') . ' jusqu\'à ' . $this->end->format('H\hi');
        }
        return $this->date->format('d/m/Y');
    }

    public function getNbPlaces(): ?int
    {
        return $this->nbPlaces;
    }

    public function setNbPlaces(?int $nbPlaces): static
    {
        $this->nbPlaces = $nbPlaces;

        return $this;
    }

    public function isOpenToAll(): ?bool
    {
        return $this->openToAll;
    }

    public function setOpenToAll(bool $openToAll): static
    {
        $this->openToAll = $openToAll;

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

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setActivity($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        if ($this->reservations->removeElement($reservation)) {
            // set the owning side to null (unless already changed)
            if ($reservation->getActivity() === $this) {
                $reservation->setActivity(null);
            }
        }

        return $this;
    }

    public function getGoogleEventId(): ?string
    {
        return $this->googleEventId;
    }

    public function setGoogleEventId(?string $googleEventId): static
    {
        $this->googleEventId = $googleEventId;

        return $this;
    }

    public function isGoogleNeedSync(): ?bool
    {
        return $this->googleNeedSync;
    }

    public function setGoogleNeedSync(bool $googleNeedSync): static
    {
        $this->googleNeedSync = $googleNeedSync;

        return $this;
    }

    public function isCanceled(): ?bool
    {
        return $this->canceled;
    }

    public function setCanceled(?bool $canceled): static
    {
        $this->canceled = $canceled;

        return $this;
    }

    public function getReasonCancel(): ?string
    {
        return $this->reasonCancel;
    }

    public function setReasonCancel(?string $reasonCancel): static
    {
        $this->reasonCancel = $reasonCancel;

        return $this;
    }
}
