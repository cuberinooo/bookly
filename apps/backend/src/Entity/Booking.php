<?php

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_BOOKING', fields: ['member', 'course'])]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['booking:read'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['booking:read', 'course:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['booking:read', 'course:read'])]
    private ?User $member = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['booking:read'])]
    private ?Course $course = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['booking:read', 'course:read'])]
    private ?bool $isWaitlist = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getMember(): ?User
    {
        return $this->member;
    }

    public function setMember(?User $member): static
    {
        $this->member = $member;

        return $this;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): static
    {
        $this->course = $course;

        return $this;
    }

    public function isWaitlist(): ?bool
    {
        return $this->isWaitlist;
    }

    public function setWaitlist(bool $isWaitlist): static
    {
        $this->isWaitlist = $isWaitlist;

        return $this;
    }
}
