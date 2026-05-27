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

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'company')]
    private Collection $users;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeAccountId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeProductSetupFeeId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeProductMembershipId = null;

    #[ORM\Column(nullable: true)]
    private ?bool $stripeOnboardingComplete = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripePriceSetupFeeId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripePriceYearlyRecurringId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripePriceMembershipId = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['company:read', 'admin:read', 'admin:write'])]
    private ?int $billingCycleAnchorDay = null;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['company:read', 'admin:read', 'admin:write'])]
    private bool $yearlyFeeEnabled = true;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->adminSettings = new AdminSettings();
        $this->globalSettings = new GlobalSettings();

        // Synchronize the inverse side
        $this->adminSettings->setCompany($this);
        $this->globalSettings->setCompany($this);
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

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setCompany($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getCompany() === $this) {
                $user->setCompany(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Course>
     */
    public function getCourses(): Collection
    {
        return $this->courses;
    }

    public function addCourse(Course $course): static
    {
        if (!$this->courses->contains($course)) {
            $this->courses->add($course);
            $course->setCompany($this);
        }

        return $this;
    }

    public function removeCourse(Course $course): static
    {
        if ($this->courses->removeElement($course)) {
            // set the owning side to null (unless already changed)
            if ($course->getCompany() === $this) {
                $course->setCompany(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CourseSeries>
     */
    public function getCourseSeries(): Collection
    {
        return $this->courseSeries;
    }

    public function addCourseSeries(CourseSeries $courseSeries): static
    {
        if (!$this->courseSeries->contains($courseSeries)) {
            $this->courseSeries->add($courseSeries);
            $courseSeries->setCompany($this);
        }

        return $this;
    }

    public function removeCourseSeries(CourseSeries $courseSeries): static
    {
        if ($this->courseSeries->removeElement($courseSeries)) {
            // set the owning side to null (unless already changed)
            if ($courseSeries->getCompany() === $this) {
                $courseSeries->setCompany(null);
            }
        }

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
