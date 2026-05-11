<?php

namespace App\Entity;

use App\Enum\RsvpStatus;
use App\Repository\MeetupRsvpRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: MeetupRsvpRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_MEETUP_USER', fields: ['meetup', 'user'])]
class MeetupRsvp implements CompanyAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['meetup:read', 'rsvp:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\ManyToOne(inversedBy: 'rsvps')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['rsvp:read'])]
    private ?Meetup $meetup = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['meetup:read', 'rsvp:read'])]
    private ?User $user = null;

    #[ORM\Column(type: "string", enumType: RsvpStatus::class)]
    #[Groups(['meetup:read', 'rsvp:read'])]
    private RsvpStatus $status = RsvpStatus::GOING;

    #[ORM\Column]
    #[Groups(['meetup:read', 'rsvp:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getMeetup(): ?Meetup
    {
        return $this->meetup;
    }

    public function setMeetup(?Meetup $meetup): static
    {
        $this->meetup = $meetup;

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

    public function getStatus(): RsvpStatus
    {
        return $this->status;
    }

    public function setStatus(RsvpStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
