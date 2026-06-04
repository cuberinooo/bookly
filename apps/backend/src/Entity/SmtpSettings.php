<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SmtpSettingsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SmtpSettingsRepository::class)]
class SmtpSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['admin:read'])]
    private ?int $id = null;

    #[ORM\OneToOne(mappedBy: 'smtpSettings', targetEntity: Company::class)]
    private ?Company $company = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read', 'admin:write'])]
    private ?string $host = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['admin:read', 'admin:write'])]
    private ?int $port = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read', 'admin:write'])]
    private ?string $username = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read', 'admin:write'])]
    private ?string $password = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['admin:read', 'admin:write'])]
    private ?string $encryption = null;

    #[ORM\Column]
    #[Groups(['admin:read', 'admin:write'])]
    private bool $useCustomSmtp = false;

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

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function setHost(?string $host): static
    {
        $this->host = $host;

        return $this;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function setPort(?int $port): static
    {
        $this->port = $port;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getEncryption(): ?string
    {
        return $this->encryption;
    }

    public function setEncryption(?string $encryption): static
    {
        $this->encryption = $encryption;

        return $this;
    }

    public function isUseCustomSmtp(): bool
    {
        return $this->useCustomSmtp;
    }

    public function setUseCustomSmtp(bool $useCustomSmtp): static
    {
        $this->useCustomSmtp = $useCustomSmtp;

        return $this;
    }
}
