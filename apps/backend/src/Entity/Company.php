<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['company:read', 'user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['company:read', 'user:read', 'admin:read', 'admin:write'])]
    private ?string $name = null;

    #[ORM\OneToOne(inversedBy: 'company', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?AdminSettings $adminSettings = null;

    #[ORM\OneToOne(inversedBy: 'company', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?GlobalSettings $globalSettings = null;

    #[ORM\OneToOne(inversedBy: 'company', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['company:read', 'admin:read'])]
    private ?StripeConfig $stripeConfig = null;

    #[ORM\OneToOne(inversedBy: 'company', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?SmtpSettings $smtpSettings = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['company:read', 'admin:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'company')]
    private Collection $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->adminSettings = new AdminSettings();
        $this->globalSettings = new GlobalSettings();
        $this->stripeConfig = new StripeConfig();
        $this->smtpSettings = new SmtpSettings();
        $this->createdAt = new \DateTimeImmutable();

        // Synchronize the inverse side
        $this->adminSettings->setCompany($this);
        $this->globalSettings->setCompany($this);
        $this->stripeConfig->setCompany($this);
        $this->smtpSettings->setCompany($this);
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAdminSettings(): ?AdminSettings
    {
        return $this->adminSettings;
    }

    public function setAdminSettings(?AdminSettings $adminSettings): static
    {
        $this->adminSettings = $adminSettings;

        return $this;
    }

    public function getGlobalSettings(): ?GlobalSettings
    {
        return $this->globalSettings;
    }

    public function setGlobalSettings(?GlobalSettings $globalSettings): static
    {
        $this->globalSettings = $globalSettings;

        return $this;
    }

    public function getStripeConfig(): ?StripeConfig
    {
        return $this->stripeConfig;
    }

    public function setStripeConfig(?StripeConfig $stripeConfig): static
    {
        $this->stripeConfig = $stripeConfig;

        return $this;
    }

    public function getSmtpSettings(): ?SmtpSettings
    {
        return $this->smtpSettings;
    }

    public function setSmtpSettings(?SmtpSettings $smtpSettings): static
    {
        $this->smtpSettings = $smtpSettings;

        return $this;
    }

    public function getSmtpUser(): ?string
    {
        return $this->smtpSettings?->getUsername();
    }

    public function getSmtpPassword(): ?string
    {
        return $this->smtpSettings?->getPassword();
    }

    public function getSmtpHost(): ?string
    {
        return $this->smtpSettings?->getHost();
    }

    public function getSmtpPort(): ?int
    {
        return $this->smtpSettings?->getPort();
    }

    public function getSmtpEncryption(): ?string
    {
        return $this->smtpSettings?->getEncryption();
    }

    public function isCustomSmtpEnabled(): bool
    {
        return $this->smtpSettings?->isUseCustomSmtp() ?? false;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }
}
