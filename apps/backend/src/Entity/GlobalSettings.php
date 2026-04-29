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

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['settings:read', 'settings:write'])]
    private ?string $legalNoticeCompanyName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['settings:read', 'settings:write'])]
    private ?string $legalNoticeRepresentative = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['settings:read', 'settings:write'])]
    private ?string $legalNoticeStreet = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['settings:read', 'settings:write'])]
    private ?string $legalNoticeHouseNumber = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['settings:read', 'settings:write'])]
    private ?string $legalNoticeZipCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['settings:read', 'settings:write'])]
    private ?string $legalNoticeCity = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['settings:read', 'settings:write'])]
    private ?string $legalNoticeEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['settings:read', 'settings:write'])]
    private ?string $legalNoticePhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['settings:read', 'settings:write'])]
    private ?string $legalNoticeTaxId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['settings:read', 'settings:write'])]
    private ?string $legalNoticeVatId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['settings:read', 'settings:write'])]
    private ?string $privacyPolicyPdfPath = null;

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

    public function getLegalNoticeCompanyName(): ?string
    {
        return $this->legalNoticeCompanyName;
    }

    public function setLegalNoticeCompanyName(?string $legalNoticeCompanyName): static
    {
        $this->legalNoticeCompanyName = $legalNoticeCompanyName;

        return $this;
    }

    public function getLegalNoticeRepresentative(): ?string
    {
        return $this->legalNoticeRepresentative;
    }

    public function setLegalNoticeRepresentative(?string $legalNoticeRepresentative): static
    {
        $this->legalNoticeRepresentative = $legalNoticeRepresentative;

        return $this;
    }

    public function getLegalNoticeStreet(): ?string
    {
        return $this->legalNoticeStreet;
    }

    public function setLegalNoticeStreet(?string $legalNoticeStreet): static
    {
        $this->legalNoticeStreet = $legalNoticeStreet;

        return $this;
    }

    public function getLegalNoticeHouseNumber(): ?string
    {
        return $this->legalNoticeHouseNumber;
    }

    public function setLegalNoticeHouseNumber(?string $legalNoticeHouseNumber): static
    {
        $this->legalNoticeHouseNumber = $legalNoticeHouseNumber;

        return $this;
    }

    public function getLegalNoticeZipCode(): ?string
    {
        return $this->legalNoticeZipCode;
    }

    public function setLegalNoticeZipCode(?string $legalNoticeZipCode): static
    {
        $this->legalNoticeZipCode = $legalNoticeZipCode;

        return $this;
    }

    public function getLegalNoticeCity(): ?string
    {
        return $this->legalNoticeCity;
    }

    public function setLegalNoticeCity(?string $legalNoticeCity): static
    {
        $this->legalNoticeCity = $legalNoticeCity;

        return $this;
    }

    public function getLegalNoticeEmail(): ?string
    {
        return $this->legalNoticeEmail;
    }

    public function setLegalNoticeEmail(?string $legalNoticeEmail): static
    {
        $this->legalNoticeEmail = $legalNoticeEmail;

        return $this;
    }

    public function getLegalNoticePhone(): ?string
    {
        return $this->legalNoticePhone;
    }

    public function setLegalNoticePhone(?string $legalNoticePhone): static
    {
        $this->legalNoticePhone = $legalNoticePhone;

        return $this;
    }

    public function getLegalNoticeTaxId(): ?string
    {
        return $this->legalNoticeTaxId;
    }

    public function setLegalNoticeTaxId(?string $legalNoticeTaxId): static
    {
        $this->legalNoticeTaxId = $legalNoticeTaxId;

        return $this;
    }

    public function getLegalNoticeVatId(): ?string
    {
        return $this->legalNoticeVatId;
    }

    public function setLegalNoticeVatId(?string $legalNoticeVatId): static
    {
        $this->legalNoticeVatId = $legalNoticeVatId;

        return $this;
    }

    public function getPrivacyPolicyPdfPath(): ?string
    {
        return $this->privacyPolicyPdfPath;
    }

    public function setPrivacyPolicyPdfPath(?string $privacyPolicyPdfPath): static
    {
        $this->privacyPolicyPdfPath = $privacyPolicyPdfPath;

        return $this;
    }
}
