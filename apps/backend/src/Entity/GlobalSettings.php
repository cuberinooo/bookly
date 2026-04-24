<?php

namespace App\Entity;

use App\Repository\GlobalSettingsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: GlobalSettingsRepository::class)]
class GlobalSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['settings:read'])]
    private ?int $id = null;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['settings:read', 'settings:write'])]
    private ?bool $showParticipantNames = true;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['settings:read', 'settings:write'])]
    private ?bool $isWaitlistVisible = true;

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

    public function isWaitlistVisible(): ?bool
    {
        return $this->isWaitlistVisible;
    }

    public function setWaitlistVisible(bool $isWaitlistVisible): static
    {
        $this->isWaitlistVisible = $isWaitlistVisible;

        return $this;
    }
}
