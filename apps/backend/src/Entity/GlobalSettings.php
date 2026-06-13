<?php

declare(strict_types=1);

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

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['settings:read', 'settings:write'])]
    private bool $autoCancelEnabled = false;

    #[ORM\Column(type: 'integer', options: ['default' => 3])]
    #[Groups(['settings:read', 'settings:write'])]
    private int $autoCancelMinParticipants = 3;

    #[ORM\Column(type: 'integer', options: ['default' => 4])]
    #[Groups(['settings:read', 'settings:write'])]
    private int $autoCancelHoursBefore = 4;

    #[ORM\Column(type: 'integer', options: ['default' => 2])]
    #[Groups(['settings:read', 'settings:write'])]
    private int $maxTrialBookingsPerClass = 2;

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

    public function isAutoCancelEnabled(): bool
    {
        return $this->autoCancelEnabled;
    }

    public function setAutoCancelEnabled(bool $autoCancelEnabled): static
    {
        $this->autoCancelEnabled = $autoCancelEnabled;

        return $this;
    }

    public function getAutoCancelMinParticipants(): int
    {
        return $this->autoCancelMinParticipants;
    }

    public function setAutoCancelMinParticipants(int $autoCancelMinParticipants): static
    {
        $this->autoCancelMinParticipants = $autoCancelMinParticipants;

        return $this;
    }

    public function getAutoCancelHoursBefore(): int
    {
        return $this->autoCancelHoursBefore;
    }

    public function setAutoCancelHoursBefore(int $autoCancelHoursBefore): static
    {
        $this->autoCancelHoursBefore = $autoCancelHoursBefore;

        return $this;
    }

    public function getMaxTrialBookingsPerClass(): int
    {
        return $this->maxTrialBookingsPerClass;
    }

    public function setMaxTrialBookingsPerClass(int $maxTrialBookingsPerClass): static
    {
        $this->maxTrialBookingsPerClass = $maxTrialBookingsPerClass;

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
