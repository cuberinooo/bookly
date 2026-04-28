<?php

namespace App\Entity;

use App\Enum\CourseFrequency;
use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CourseRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['course:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'courses')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['course:read'])]
    private ?CourseSeries $series = null;

    #[ORM\Column(length: 255)]
    #[Groups(['course:read', 'course:write'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['course:read', 'course:write'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['course:read', 'course:write'])]
    private ?int $capacity = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['course:read', 'course:write'])]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['course:read', 'course:write'])]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['course:read', 'course:write'])]
    private ?int $durationMinutes = null;

    #[ORM\Column(type: "string", enumType: CourseFrequency::class)]
    #[Groups(['course:read', 'course:write'])]
    private CourseFrequency $frequency = CourseFrequency::ONCE;

    #[ORM\ManyToOne(inversedBy: 'courses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['course:read'])]
    private ?User $trainer = null;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'course', orphanRemoval: true)]
    #[Groups(['course:read'])]
    private Collection $bookings;

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(?int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;

        return $this;
    }

    public function getFrequency(): CourseFrequency
    {
        return $this->frequency;
    }

    public function setFrequency(CourseFrequency $frequency): static
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function getTrainer(): ?User
    {
        return $this->trainer;
    }

    public function setTrainer(?User $trainer): static
    {
        $this->trainer = $trainer;

        return $this;
    }

    public function getSeries(): ?CourseSeries
    {
        return $this->series;
    }

    public function setSeries(?CourseSeries $series): static
    {
        $this->series = $series;

        return $this;
    }

    #[Groups(['course:read'])]
    public function getSeriesId(): ?string
    {
        return $this->series ? (string) $this->series->getId() : null;
    }

    #[ORM\PreUpdate]
    public function validateFrequency(\Doctrine\ORM\Event\PreUpdateEventArgs $event): void
    {
        if ($event->hasChangedField('frequency')) {
            throw new \LogicException('The frequency of a course cannot be changed once created.');
        }
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setCourse($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            // set the owning side to null (unless already changed)
            if ($booking->getCourse() === $this) {
                $booking->setCourse(null);
            }
        }

        return $this;
    }
}
