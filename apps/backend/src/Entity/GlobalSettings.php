<?php

namespace App\Entity;

use App\Enum\BookingWindow;
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

    #[ORM\Column(type: 'string', enumType: BookingWindow::class, options: ['default' => BookingWindow::OFF])]
    #[Groups(['settings:read', 'settings:write'])]
    private BookingWindow $bookingWindow = BookingWindow::OFF;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['settings:read', 'settings:write'])]
    private int $trialBookingLimit = 0;

    #[ORM\OneToOne(mappedBy: 'globalSettings', targetEntity: Company::class)]
    private ?Company $company = null;

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

    public function getBookingWindow(): BookingWindow
    {
        return $this->bookingWindow;
    }

    public function setBookingWindow(BookingWindow $bookingWindow): static
    {
        $this->bookingWindow = $bookingWindow;

        return $this;
    }

    public function getTrialBookingLimit(): int
    {
        return $this->trialBookingLimit;
    }

    public function setTrialBookingLimit(int $limit): static
    {
        $this->trialBookingLimit = $limit;

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
