<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, CompanyAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['course:read', 'booking:read', 'user:read', 'meetup:read', 'workout_record:read', 'comment:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Groups(['course:read', 'booking:read', 'user:read'])]
    private ?string $email = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['user:read'])]
    private ?Company $company = null;

    #[Groups(['course:read', 'booking:read', 'user:read', 'meetup:read', 'workout_record:read', 'comment:read'])]
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var list<string> The user roles
     */
    #[Groups(['course:read', 'booking:read', 'user:read'])]
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    /**
     * @var Collection<int, Course>
     */
    #[ORM\OneToMany(targetEntity: Course::class, mappedBy: 'user', cascade: ['remove'])]
    private Collection $courses;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'user', cascade: ['remove'])]
    private Collection $bookings;

    /**
     * @var Collection<int, Meetup>
     */
    #[ORM\OneToMany(targetEntity: Meetup::class, mappedBy: 'creator', cascade: ['remove'])]
    private Collection $meetups;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['user:read'])]
    private ?bool $isVerified = false;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['user:read'])]
    private ?bool $isActive = true;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['user:read'])]
    private ?bool $mustChangePassword = false;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['user:read'])]
    private int $courseStartNotificationHours = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['user:read'])]
    private int $courseStartNotificationMinutes = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $verificationToken = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $verificationTokenExpiresAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordResetToken = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $passwordResetTokenExpiresAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:read', 'meetup:read', 'workout_record:read', 'comment:read'])]
    private ?string $profilePicture = null;

    #[ORM\Column(name: 'gender', type: 'string', enumType: \App\Enum\Gender::class, nullable: true)]
    #[Groups(['user:read'])]
    private ?\App\Enum\Gender $gender = null;

    #[ORM\Column(name: 'is_public', type: 'boolean', options: ['default' => false])]
    #[Groups(['user:read'])]
    private bool $isPublic = false;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phoneNumber = null;

    public function getGender(): ?\App\Enum\Gender
    {
        return $this->gender;
    }

    public function setGender(?\App\Enum\Gender $gender): static
    {
        $this->gender = $gender;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;

        return $this;
    }

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emergencyContactName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $emergencyContactPhone = null;

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getEmergencyContactName(): ?string
    {
        return $this->emergencyContactName;
    }

    public function setEmergencyContactName(?string $emergencyContactName): static
    {
        $this->emergencyContactName = $emergencyContactName;

        return $this;
    }

    public function getEmergencyContactPhone(): ?string
    {
        return $this->emergencyContactPhone;
    }

    public function setEmergencyContactPhone(?string $emergencyContactPhone): static
    {
        $this->emergencyContactPhone = $emergencyContactPhone;

        return $this;
    }

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(?string $profilePicture): static
    {
        $this->profilePicture = $profilePicture;

        return $this;
    }

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['user:read'])]
    private bool $joinUsMailSent = false;

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    #[Groups(['user:read'])]
    private array $onboardingState = [];

    public function getOnboardingState(): array
    {
        return $this->onboardingState;
    }

    public function setOnboardingState(array $onboardingState): static
    {
        $this->onboardingState = $onboardingState;

        return $this;
    }

    public function isJoinUsMailSent(): bool
    {
        return $this->joinUsMailSent;
    }

    public function setJoinUsMailSent(bool $joinUsMailSent): static
    {
        $this->joinUsMailSent = $joinUsMailSent;

        return $this;
    }

    public function __construct()
    {
        $this->courses = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->meetups = new ArrayCollection();
    }

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isMustChangePassword(): ?bool
    {
        return $this->mustChangePassword;
    }

    public function setMustChangePassword(bool $mustChangePassword): static
    {
        $this->mustChangePassword = $mustChangePassword;

        return $this;
    }

    public function getCourseStartNotificationHours(): int
    {
        return $this->courseStartNotificationHours;
    }

    public function setCourseStartNotificationHours(int $hours): static
    {
        $this->courseStartNotificationHours = $hours;

        return $this;
    }

    public function getCourseStartNotificationMinutes(): int
    {
        return $this->courseStartNotificationMinutes;
    }

    public function setCourseStartNotificationMinutes(int $minutes): static
    {
        $this->courseStartNotificationMinutes = $minutes;

        return $this;
    }

    public function getVerificationToken(): ?string
    {
        return $this->verificationToken;
    }

    public function setVerificationToken(?string $verificationToken): static
    {
        $this->verificationToken = $verificationToken;

        return $this;
    }

    public function getVerificationTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->verificationTokenExpiresAt;
    }

    public function setVerificationTokenExpiresAt(?\DateTimeInterface $verificationTokenExpiresAt): static
    {
        $this->verificationTokenExpiresAt = $verificationTokenExpiresAt;

        return $this;
    }

    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    public function setPasswordResetToken(?string $passwordResetToken): static
    {
        $this->passwordResetToken = $passwordResetToken;

        return $this;
    }

    public function getPasswordResetTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->passwordResetTokenExpiresAt;
    }

    public function setPasswordResetTokenExpiresAt(?\DateTimeInterface $passwordResetTokenExpiresAt): static
    {
        $this->passwordResetTokenExpiresAt = $passwordResetTokenExpiresAt;

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
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

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

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
            $course->setUser($this);
        }

        return $this;
    }

    public function removeCourse(Course $course): static
    {
        if ($this->courses->removeElement($course)) {
            // set the owning side to null (unless already changed)
            if ($course->getUser() === $this) {
                $course->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setUser($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            // set the owning side to null (unless already changed)
            if ($booking->getUser() === $this) {
                $booking->setUser(null);
            }
        }

        return $this;
    }

    #[Groups(['user:read'])]
    public function getBookingCount(): int
    {
        return $this->bookings->count();
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }
}
