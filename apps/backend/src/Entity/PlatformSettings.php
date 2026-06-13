<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlatformSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: PlatformSettingsRepository::class)]
class PlatformSettings
{
    public function __construct()
    {
        $this->operatorName = 'Kubilay Anil';
        $this->operatorCompany = 'IT-Dienstleistungen Kubilay Anil';
        $this->operatorDetails = 'Entwicklung, Vertrieb und Betrieb von Software, Web- und mobilen Applikationen (SaaS), Erbringung von IT-Dienstleistungen, IT-Beratung sowie der Betrieb von Webportalen.';
        $this->operatorStreet = 'Kreuzstr.';
        $this->operatorHouseNumber = '19';
        $this->operatorZipCode = '89160';
        $this->operatorCity = 'Dornstadt';
        $this->operatorEmail = 'kubilay.anil@codingcube.de';
        $this->operatorPhone = '01627895106';
        $this->profession = 'Softwareentwickler';
        $this->country = 'Deutschland';
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['platform:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['platform:read', 'platform:write'])]
    private ?string $operatorName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['platform:read', 'platform:write'])]
    private ?string $operatorCompany = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['platform:read', 'platform:write'])]
    private ?string $operatorDetails = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['platform:read', 'platform:write'])]
    private ?string $operatorStreet = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['platform:read', 'platform:write'])]
    private ?string $operatorHouseNumber = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['platform:read', 'platform:write'])]
    private ?string $operatorZipCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['platform:read', 'platform:write'])]
    private ?string $operatorCity = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['platform:read', 'platform:write'])]
    private ?string $operatorEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['platform:read', 'platform:write'])]
    private ?string $operatorPhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['platform:read', 'platform:write'])]
    private ?string $profession = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['platform:read', 'platform:write'])]
    private ?string $country = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['platform:read', 'platform:write'])]
    private ?string $taxId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['platform:read', 'platform:write'])]
    private ?string $vatId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['platform:read', 'platform:write'])]
    private ?string $privacyPolicyPdfPath = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOperatorName(): ?string
    {
        return $this->operatorName;
    }

    public function setOperatorName(?string $operatorName): static
    {
        $this->operatorName = $operatorName;

        return $this;
    }

    public function getOperatorCompany(): ?string
    {
        return $this->operatorCompany;
    }

    public function setOperatorCompany(?string $operatorCompany): static
    {
        $this->operatorCompany = $operatorCompany;

        return $this;
    }

    public function getOperatorDetails(): ?string
    {
        return $this->operatorDetails;
    }

    public function setOperatorDetails(?string $operatorDetails): static
    {
        $this->operatorDetails = $operatorDetails;

        return $this;
    }

    public function getOperatorStreet(): ?string
    {
        return $this->operatorStreet;
    }

    public function setOperatorStreet(?string $operatorStreet): static
    {
        $this->operatorStreet = $operatorStreet;

        return $this;
    }

    public function getOperatorHouseNumber(): ?string
    {
        return $this->operatorHouseNumber;
    }

    public function setOperatorHouseNumber(?string $operatorHouseNumber): static
    {
        $this->operatorHouseNumber = $operatorHouseNumber;

        return $this;
    }

    public function getOperatorZipCode(): ?string
    {
        return $this->operatorZipCode;
    }

    public function setOperatorZipCode(?string $operatorZipCode): static
    {
        $this->operatorZipCode = $operatorZipCode;

        return $this;
    }

    public function getOperatorCity(): ?string
    {
        return $this->operatorCity;
    }

    public function setOperatorCity(?string $operatorCity): static
    {
        $this->operatorCity = $operatorCity;

        return $this;
    }

    public function getOperatorEmail(): ?string
    {
        return $this->operatorEmail;
    }

    public function setOperatorEmail(?string $operatorEmail): static
    {
        $this->operatorEmail = $operatorEmail;

        return $this;
    }

    public function getOperatorPhone(): ?string
    {
        return $this->operatorPhone;
    }

    public function setOperatorPhone(?string $operatorPhone): static
    {
        $this->operatorPhone = $operatorPhone;

        return $this;
    }

    public function getProfession(): ?string
    {
        return $this->profession;
    }

    public function setProfession(?string $profession): static
    {
        $this->profession = $profession;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getTaxId(): ?string
    {
        return $this->taxId;
    }

    public function setTaxId(?string $taxId): static
    {
        $this->taxId = $taxId;

        return $this;
    }

    public function getVatId(): ?string
    {
        return $this->vatId;
    }

    public function setVatId(?string $vatId): static
    {
        $this->vatId = $vatId;

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
