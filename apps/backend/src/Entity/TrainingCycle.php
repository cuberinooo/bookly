<?php

namespace App\Entity;

use App\Repository\TrainingCycleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TrainingCycleRepository::class)]
class TrainingCycle implements CompanyAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['cycle:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $trainer = null;

    #[ORM\Column(length: 255)]
    #[Groups(['cycle:read'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['cycle:read'])]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column]
    #[Groups(['cycle:read'])]
    private ?int $durationWeeks = 4;

    #[ORM\Column]
    #[Groups(['cycle:read'])]
    private ?bool $isActive = true;

    /**
     * @var Collection<int, CycleAssignment>
     */
    #[ORM\OneToMany(targetEntity: CycleAssignment::class, mappedBy: 'cycle', cascade: ['persist', 'remove'])]
    #[Groups(['cycle:read'])]
    private Collection $assignments;

    public function __construct()
    {
        $this->assignments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTrainer(): ?User
    {
        return $this->trainer;
    }

    public function setTrainer(?User $trainer): static
    {
        $this->trainer = $trainer;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getDurationWeeks(): ?int
    {
        return $this->durationWeeks;
    }

    public function setDurationWeeks(int $durationWeeks): static
    {
        $this->durationWeeks = $durationWeeks;

        return $this;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return Collection<int, CycleAssignment>
     */
    public function getAssignments(): Collection
    {
        return $this->assignments;
    }

    public function addAssignment(CycleAssignment $assignment): static
    {
        if (!$this->assignments->contains($assignment)) {
            $this->assignments->add($assignment);
            $assignment->setCycle($this);
        }

        return $this;
    }

    public function removeAssignment(CycleAssignment $assignment): static
    {
        if ($this->assignments->removeElement($assignment)) {
            // set the owning side to null (unless already changed)
            if ($assignment->getCycle() === $this) {
                $assignment->setCycle(null);
            }
        }

        return $this;
    }
}
