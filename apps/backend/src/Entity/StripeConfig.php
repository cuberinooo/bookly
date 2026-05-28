<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StripeConfigRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: StripeConfigRepository::class)]
class StripeConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['company:read', 'admin:read'])]
    private ?int $id = null;

    #[ORM\OneToOne(mappedBy: 'stripeConfig', targetEntity: Company::class)]
    private ?Company $company = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read'])]
    private ?string $stripeAccountId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read'])]
    private ?string $stripeProductSetupFeeId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read'])]
    private ?string $stripeProductMembershipId = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['admin:read'])]
    private ?bool $stripeOnboardingComplete = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read'])]
    private ?string $stripePriceSetupFeeId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read'])]
    private ?string $stripePriceYearlyRecurringId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read'])]
    private ?string $stripePriceMembershipId = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['company:read', 'admin:read', 'admin:write'])]
    private ?int $billingCycleAnchorDay = null;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['company:read', 'admin:read', 'admin:write'])]
    private bool $yearlyFeeEnabled = true;

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

    public function getStripeAccountId(): ?string
    {
        return $this->stripeAccountId;
    }

    public function setStripeAccountId(?string $stripeAccountId): static
    {
        $this->stripeAccountId = $stripeAccountId;

        return $this;
    }

    public function getStripeProductSetupFeeId(): ?string
    {
        return $this->stripeProductSetupFeeId;
    }

    public function setStripeProductSetupFeeId(?string $stripeProductSetupFeeId): static
    {
        $this->stripeProductSetupFeeId = $stripeProductSetupFeeId;

        return $this;
    }

    public function getStripeProductMembershipId(): ?string
    {
        return $this->stripeProductMembershipId;
    }

    public function setStripeProductMembershipId(?string $stripeProductMembershipId): static
    {
        $this->stripeProductMembershipId = $stripeProductMembershipId;

        return $this;
    }

    public function isStripeOnboardingComplete(): ?bool
    {
        return $this->stripeOnboardingComplete;
    }

    public function setStripeOnboardingComplete(?bool $stripeOnboardingComplete): static
    {
        $this->stripeOnboardingComplete = $stripeOnboardingComplete;

        return $this;
    }

    public function getStripePriceSetupFeeId(): ?string
    {
        return $this->stripePriceSetupFeeId;
    }

    public function setStripePriceSetupFeeId(?string $stripePriceSetupFeeId): static
    {
        $this->stripePriceSetupFeeId = $stripePriceSetupFeeId;

        return $this;
    }

    public function getStripePriceYearlyRecurringId(): ?string
    {
        return $this->stripePriceYearlyRecurringId;
    }

    public function setStripePriceYearlyRecurringId(?string $stripePriceYearlyRecurringId): static
    {
        $this->stripePriceYearlyRecurringId = $stripePriceYearlyRecurringId;

        return $this;
    }

    public function getStripePriceMembershipId(): ?string
    {
        return $this->stripePriceMembershipId;
    }

    public function setStripePriceMembershipId(?string $stripePriceMembershipId): static
    {
        $this->stripePriceMembershipId = $stripePriceMembershipId;

        return $this;
    }

    public function getBillingCycleAnchorDay(): ?int
    {
        return $this->billingCycleAnchorDay;
    }

    public function setBillingCycleAnchorDay(?int $billingCycleAnchorDay): static
    {
        $this->billingCycleAnchorDay = $billingCycleAnchorDay;

        return $this;
    }

    public function isYearlyFeeEnabled(): bool
    {
        return $this->yearlyFeeEnabled;
    }

    public function setYearlyFeeEnabled(bool $yearlyFeeEnabled): static
    {
        $this->yearlyFeeEnabled = $yearlyFeeEnabled;

        return $this;
    }
}
