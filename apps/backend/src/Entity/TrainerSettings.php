<?php

namespace App\Entity;

use App\Repository\TrainerSettingsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TrainerSettingsRepository::class)]
class TrainerSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read', 'course:read'])]
    private ?int $id = null;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['user:read', 'course:read'])]
    private ?bool $showParticipantNames = true;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['user:read', 'course:read'])]
    private ?bool $showWaitlistNames = true;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['user:read', 'course:read'])]
    private ?bool $isWaitlistVisible = true;

    #[ORM\OneToOne(inversedBy: 'trainerSettings', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $trainer = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isShowParticipantNames(): ?bool
    {
        return $this->showParticipantNames;
    }

    public function setShowParticipantNames(bool $showParticipantNames): static
    {
        $this->showParticipantNames = $showParticipantNames;

        return $this;
    }

    public function isShowWaitlistNames(): ?bool
    {
        return $this->showWaitlistNames;
    }

    public function setShowWaitlistNames(bool $showWaitlistNames): static
    {
        $this->showWaitlistNames = $showWaitlistNames;

        return $this;
    }

    public function isWaitlistVisible(): ?bool
    {
        return $this->isWaitlistVisible;
    }

    public function setWaitlistVisible(bool $isWaitlistVisible): static
    {
        $this->isWaitlistVisible = $isWaitlistVisible;

        return $this;
    }

    public function getTrainer(): ?User
    {
        return $this->trainer;
    }

    public function setTrainer(User $trainer): static
    {
        $this->trainer = $trainer;

        return $this;
    }
}
