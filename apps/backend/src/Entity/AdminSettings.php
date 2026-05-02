<?php

namespace App\Entity;

use App\Repository\AdminSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AdminSettingsRepository::class)]
class AdminSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['admin:read'])]
    private ?int $id = null;

    #[ORM\OneToOne(mappedBy: 'adminSettings', targetEntity: Company::class)]
    private ?Company $company = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read', 'admin:write'])]
    private ?string $legalNoticeRepresentative = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read', 'admin:write'])]
    private ?string $legalNoticeStreet = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['admin:read', 'admin:write'])]
    private ?string $legalNoticeHouseNumber = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['admin:read', 'admin:write'])]
    private ?string $legalNoticeZipCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read', 'admin:write'])]
    private ?string $legalNoticeCity = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read', 'admin:write'])]
    private ?string $legalNoticeEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read', 'admin:write'])]
    private ?string $legalNoticePhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read', 'admin:write'])]
    private ?string $legalNoticeTaxId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read', 'admin:write'])]
    private ?string $legalNoticeVatId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read', 'admin:write'])]
    private ?string $privacyPolicyPdfPath = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['admin:read', 'admin:write'])]
    private ?string $legalNoticeMarkdown = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['admin:read', 'admin:write'])]
    private ?string $termsAndConditionsMarkdown = null;

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

    public function getLegalNoticeMarkdown(): ?string
    {
        return $this->legalNoticeMarkdown;
    }

    public function setLegalNoticeMarkdown(?string $legalNoticeMarkdown): static
    {
        $this->legalNoticeMarkdown = $legalNoticeMarkdown;
        return $this;
    }

    public function getTermsAndConditionsMarkdown(): ?string
    {
        return $this->termsAndConditionsMarkdown;
    }

    public function setTermsAndConditionsMarkdown(?string $termsAndConditionsMarkdown): static
    {
        $this->termsAndConditionsMarkdown = $termsAndConditionsMarkdown;
        return $this;
    }
}
