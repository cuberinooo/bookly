<?php

namespace App\Entity;

use App\Enum\CourseFrequency;
use App\Repository\CourseSeriesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CourseSeriesRepository::class)]
class CourseSeries implements CompanyAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['course:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\Column(length: 255)]
    #[Groups(['course:read'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['course:read'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['course:read'])]
    private ?int $capacity = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['course:read'])]
    private ?\DateTimeInterface $scheduleStartTime = null;

    #[ORM\Column]
    #[Groups(['course:read'])]
    private ?int $durationMinutes = null;

    #[ORM\Column(type: "string", enumType: CourseFrequency::class)]
    #[Groups(['course:read'])]
    private CourseFrequency $frequency = CourseFrequency::ONCE;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['course:read'])]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $lastGeneratedDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column]
    private bool $active = true;

    /**
     * @var Collection<int, Course>
     */
    #[ORM\OneToMany(targetEntity: Course::class, mappedBy: 'series', orphanRemoval: true)]
    private Collection $courses;

    public function __construct()
    {
        $this->courses = new ArrayCollection();
        $this->createdAt = new \DateTime();
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

    public function getScheduleStartTime(): ?\DateTimeInterface
    {
        return $this->scheduleStartTime;
    }

    public function setScheduleStartTime(\DateTimeInterface $scheduleStartTime): static
    {
        $this->scheduleStartTime = $scheduleStartTime;
        return $this;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): static
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getLastGeneratedDate(): ?\DateTimeInterface
    {
        return $this->lastGeneratedDate;
    }

    public function setLastGeneratedDate(?\DateTimeInterface $lastGeneratedDate): static
    {
        $this->lastGeneratedDate = $lastGeneratedDate;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;
        return $this;
    }

    /**
     * @return Collection<int, Course>
     */
    public function getCourses(): Collection
    {
        return $this->courses;
    }

    public function addCourse(Course $course): static
    {
        if (!$this->courses->contains($course)) {
            $this->courses->add($course);
            $course->setSeries($this);
        }
        return $this;
    }

    public function removeCourse(Course $course): static
    {
        if ($this->courses->removeElement($course)) {
            if ($course->getSeries() === $this) {
                $course->setSeries(null);
            }
        }
        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): static
    {
        $this->company = $company;
        return $this;
    }
}
