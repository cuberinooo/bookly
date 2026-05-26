<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserWorkoutRecordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: UserWorkoutRecordRepository::class)]
class UserWorkoutRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['workout_record:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['workout_record:read'])]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    #[Groups(['workout_record:read'])]
    private ?string $exerciseName = null;

    #[ORM\Column]
    #[Groups(['workout_record:read'])]
    private ?float $weightValue = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['workout_record:read'])]
    private ?\DateTimeInterface $dateAchieved = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getExerciseName(): ?string
    {
        return $this->exerciseName;
    }

    public function setExerciseName(string $exerciseName): static
    {
        $this->exerciseName = $exerciseName;

        return $this;
    }

    public function getWeightValue(): ?float
    {
        return $this->weightValue;
    }

    public function setWeightValue(float $weightValue): static
    {
        $this->weightValue = $weightValue;

        return $this;
    }

    public function getDateAchieved(): ?\DateTimeInterface
    {
        return $this->dateAchieved;
    }

    public function setDateAchieved(\DateTimeInterface $dateAchieved): static
    {
        $this->dateAchieved = $dateAchieved;

        return $this;
    }
}
