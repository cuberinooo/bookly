<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SensitiveDataAccessLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SensitiveDataAccessLogRepository::class)]
class SensitiveDataAccessLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $viewer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $targetUser = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $accessedAt = null;

    #[ORM\Column(length: 255)]
    private ?string $reason = null;

    public function __construct()
    {
        $this->accessedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getViewer(): ?User
    {
        return $this->viewer;
    }

    public function setViewer(?User $viewer): static
    {
        $this->viewer = $viewer;

        return $this;
    }

    public function getTargetUser(): ?User
    {
        return $this->targetUser;
    }

    public function setTargetUser(?User $targetUser): static
    {
        $this->targetUser = $targetUser;

        return $this;
    }

    public function getAccessedAt(): ?\DateTimeImmutable
    {
        return $this->accessedAt;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }
}
