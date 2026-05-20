<?php

namespace App\Entity;

use App\Repository\CycleAssignmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CycleAssignmentRepository::class)]
class CycleAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cycle:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'assignments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TrainingCycle $cycle = null;

    #[ORM\Column]
    #[Groups(['cycle:read'])]
    private ?int $weekNumber = null;

    #[ORM\Column]
    #[Groups(['cycle:read'])]
    private ?int $dayOfWeek = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['cycle:read'])]
    private ?TrainingCategory $category = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCycle(): ?TrainingCycle
    {
        return $this->cycle;
    }

    public function setCycle(?TrainingCycle $cycle): static
    {
        $this->cycle = $cycle;

        return $this;
    }

    public function getWeekNumber(): ?int
    {
        return $this->weekNumber;
    }

    public function setWeekNumber(int $weekNumber): static
    {
        $this->weekNumber = $weekNumber;

        return $this;
    }

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(int $dayOfWeek): static
    {
        $this->dayOfWeek = $dayOfWeek;

        return $this;
    }

    public function getCategory(): ?TrainingCategory
    {
        return $this->category;
    }

    public function setCategory(?TrainingCategory $category): static
    {
        $this->category = $category;

        return $this;
    }
}
