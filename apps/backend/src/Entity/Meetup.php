<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MeetupStatus;
use App\Repository\MeetupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: MeetupRepository::class)]
class Meetup implements CompanyAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['meetup:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['meetup:read'])]
    private ?Company $company = null;

    #[ORM\ManyToOne(inversedBy: 'meetups')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['meetup:read'])]
    private ?User $creator = null;

    #[ORM\Column(length: 255)]
    #[Groups(['meetup:read', 'meetup:write'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['meetup:read', 'meetup:write'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['meetup:read', 'meetup:write'])]
    private ?\DateTimeInterface $meetupDate = null;

    #[ORM\Column(length: 1000)]
    #[Groups(['meetup:read', 'meetup:write'])]
    private ?string $location = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['meetup:read', 'meetup:write'])]
    private ?string $imageUrl = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['meetup:read', 'meetup:write'])]
    private ?int $minParticipants = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['meetup:read', 'meetup:write'])]
    private ?int $maxParticipants = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['meetup:read', 'meetup:write'])]
    private ?\DateTimeInterface $rsvpDeadline = null;

    #[ORM\Column(type: 'string', enumType: MeetupStatus::class)]
    #[Groups(['meetup:read'])]
    private MeetupStatus $status = MeetupStatus::OPEN;

    #[ORM\Column]
    #[Groups(['meetup:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, MeetupRsvp>
     */
    #[ORM\OneToMany(targetEntity: MeetupRsvp::class, mappedBy: 'meetup', orphanRemoval: true)]
    #[Groups(['meetup:read'])]
    private Collection $rsvps;

    #[ORM\Column(length: 1000, nullable: true)]
    #[Groups(['meetup:read', 'meetup:write'])]
    private ?string $link = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->rsvps = new ArrayCollection();
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

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setCreator(?User $creator): static
    {
        $this->creator = $creator;

        return $this;
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

    public function getMeetupDate(): ?\DateTimeInterface
    {
        return $this->meetupDate;
    }

    public function setMeetupDate(\DateTimeInterface $meetupDate): static
    {
        $this->meetupDate = $meetupDate;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getMinParticipants(): ?int
    {
        return $this->minParticipants;
    }

    public function setMinParticipants(?int $minParticipants): static
    {
        $this->minParticipants = $minParticipants;

        return $this;
    }

    public function getMaxParticipants(): ?int
    {
        return $this->maxParticipants;
    }

    public function setMaxParticipants(?int $maxParticipants): static
    {
        $this->maxParticipants = $maxParticipants;

        return $this;
    }

    public function getRsvpDeadline(): ?\DateTimeInterface
    {
        return $this->rsvpDeadline;
    }

    public function setRsvpDeadline(\DateTimeInterface $rsvpDeadline): static
    {
        $this->rsvpDeadline = $rsvpDeadline;

        return $this;
    }

    public function getStatus(): MeetupStatus
    {
        return $this->status;
    }

    public function setStatus(MeetupStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, MeetupRsvp>
     */
    public function getRsvps(): Collection
    {
        return $this->rsvps;
    }

    public function addRsvp(MeetupRsvp $rsvp): static
    {
        if (!$this->rsvps->contains($rsvp)) {
            $this->rsvps->add($rsvp);
            $rsvp->setMeetup($this);
        }

        return $this;
    }

    public function removeRsvp(MeetupRsvp $rsvp): static
    {
        if ($this->rsvps->removeElement($rsvp)) {
            // set the owning side to null (unless already changed)
            if ($rsvp->getMeetup() === $this) {
                $rsvp->setMeetup(null);
            }
        }

        return $this;
    }

    #[Groups(['meetup:read'])]
    public function getGoingCount(): int
    {
        return $this->rsvps->filter(fn (MeetupRsvp $rsvp) => \App\Enum\RsvpStatus::GOING === $rsvp->getStatus())->count();
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): static
    {
        $this->link = $link;

        return $this;
    }
}
