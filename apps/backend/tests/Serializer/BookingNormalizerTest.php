<?php

declare(strict_types=1);

namespace App\Tests\Serializer;

use App\Entity\Booking;
use App\Entity\Company;
use App\Entity\Course;
use App\Entity\GlobalSettings;
use App\Entity\User;
use App\Repository\GlobalSettingsRepository;
use App\Serializer\BookingNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class BookingNormalizerTest extends TestCase
{
    private $objectNormalizer;
    private $security;
    private $settingsRepository;
    private $normalizer;

    protected function setUp(): void
    {
        $this->objectNormalizer = $this->createMock(NormalizerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->settingsRepository = $this->createMock(GlobalSettingsRepository::class);
        $this->normalizer = new BookingNormalizer(
            $this->objectNormalizer,
            $this->security,
            $this->settingsRepository
        );
    }

    public function test_supports_normalization(): void
    {
        $booking = $this->createMock(Booking::class);
        $this->assertTrue($this->normalizer->supportsNormalization($booking));
        $this->assertFalse($this->normalizer->supportsNormalization($booking, null, ['booking_normalizer_already_called' => true]));
    }

    public function test_normalize_shows_real_names_and_roles_when_settings_allow(): void
    {
        $bookingUser = $this->createMock(User::class);
        $bookingUser->method('getId')->willReturn(42);
        $bookingUser->method('getUserIdentifier')->willReturn('member@example.com');

        $trainer = $this->createMock(User::class);
        $trainer->method('getUserIdentifier')->willReturn('trainer@example.com');

        $globalSettings = $this->createMock(GlobalSettings::class);
        $globalSettings->method('getId')->willReturn(1);
        $globalSettings->method('isWaitlistVisible')->willReturn(true);
        $globalSettings->method('isShowParticipantNames')->willReturn(true);

        $company = $this->createMock(Company::class);
        $company->method('getGlobalSettings')->willReturn($globalSettings);
        $trainer->method('getCompany')->willReturn($company);

        $course = $this->createMock(Course::class);
        $course->method('getUser')->willReturn($trainer);

        $booking = $this->createMock(Booking::class);
        $booking->method('getCourse')->willReturn($course);
        $booking->method('getUser')->willReturn($bookingUser);
        $booking->method('isWaitlist')->willReturn(false);

        // Current user is a regular member (neither trainer nor owner of the booking)
        $currentUser = $this->createMock(User::class);
        $currentUser->method('getUserIdentifier')->willReturn('other@example.com');

        $this->security->method('getUser')->willReturn($currentUser);
        $this->settingsRepository->method('find')->with(1)->willReturn($globalSettings);

        $initialNormalized = [
            'id' => 10,
            'user' => [
                'id' => 42,
                'name' => 'Real Name',
                'email' => 'member@example.com',
                'roles' => ['ROLE_TRIAL', 'ROLE_USER']
            ]
        ];

        $this->objectNormalizer->expects($this->once())
            ->method('normalize')
            ->willReturn($initialNormalized);

        $result = $this->normalizer->normalize($booking);

        $this->assertSame('Real Name', $result['user']['name']);
        $this->assertSame(['ROLE_TRIAL', 'ROLE_USER'], $result['user']['roles']);
        // Email should be hidden for others
        $this->assertArrayNotHasKey('email', $result['user']);
    }

    public function test_normalize_anonymizes_names_and_strips_roles_when_settings_restrict(): void
    {
        $bookingUser = $this->createMock(User::class);
        $bookingUser->method('getId')->willReturn(42);
        $bookingUser->method('getUserIdentifier')->willReturn('member@example.com');

        $trainer = $this->createMock(User::class);
        $trainer->method('getUserIdentifier')->willReturn('trainer@example.com');

        $globalSettings = $this->createMock(GlobalSettings::class);
        $globalSettings->method('getId')->willReturn(1);
        $globalSettings->method('isWaitlistVisible')->willReturn(true);
        $globalSettings->method('isShowParticipantNames')->willReturn(false);

        $company = $this->createMock(Company::class);
        $company->method('getGlobalSettings')->willReturn($globalSettings);
        $trainer->method('getCompany')->willReturn($company);

        $course = $this->createMock(Course::class);
        $course->method('getUser')->willReturn($trainer);

        $booking = $this->createMock(Booking::class);
        $booking->method('getCourse')->willReturn($course);
        $booking->method('getUser')->willReturn($bookingUser);
        $booking->method('isWaitlist')->willReturn(false);

        // Current user is a regular member (neither trainer nor owner)
        $currentUser = $this->createMock(User::class);
        $currentUser->method('getUserIdentifier')->willReturn('other@example.com');

        $this->security->method('getUser')->willReturn($currentUser);
        $this->settingsRepository->method('find')->with(1)->willReturn($globalSettings);

        $initialNormalized = [
            'id' => 10,
            'user' => [
                'id' => 42,
                'name' => 'Real Name',
                'email' => 'member@example.com',
                'roles' => ['ROLE_TRIAL', 'ROLE_USER']
            ]
        ];

        $this->objectNormalizer->expects($this->once())
            ->method('normalize')
            ->willReturn($initialNormalized);

        $result = $this->normalizer->normalize($booking);

        $this->assertSame('Athlete #42', $result['user']['name']);
        $this->assertArrayNotHasKey('roles', $result['user']);
        $this->assertArrayNotHasKey('email', $result['user']);
    }

    public function test_normalize_shows_real_names_and_roles_to_trainer_regardless_of_settings(): void
    {
        $bookingUser = $this->createMock(User::class);
        $bookingUser->method('getId')->willReturn(42);
        $bookingUser->method('getUserIdentifier')->willReturn('member@example.com');

        $trainer = $this->createMock(User::class);
        $trainer->method('getUserIdentifier')->willReturn('trainer@example.com');

        $globalSettings = $this->createMock(GlobalSettings::class);
        $globalSettings->method('getId')->willReturn(1);
        $globalSettings->method('isWaitlistVisible')->willReturn(true);
        $globalSettings->method('isShowParticipantNames')->willReturn(false); // setting is false

        $company = $this->createMock(Company::class);
        $company->method('getGlobalSettings')->willReturn($globalSettings);
        $trainer->method('getCompany')->willReturn($company);

        $course = $this->createMock(Course::class);
        $course->method('getUser')->willReturn($trainer);

        $booking = $this->createMock(Booking::class);
        $booking->method('getCourse')->willReturn($course);
        $booking->method('getUser')->willReturn($bookingUser);
        $booking->method('isWaitlist')->willReturn(false);

        // Current user is the trainer
        $this->security->method('getUser')->willReturn($trainer);
        $this->settingsRepository->method('find')->with(1)->willReturn($globalSettings);

        $initialNormalized = [
            'id' => 10,
            'user' => [
                'id' => 42,
                'name' => 'Real Name',
                'email' => 'member@example.com',
                'roles' => ['ROLE_TRIAL', 'ROLE_USER']
            ]
        ];

        $this->objectNormalizer->expects($this->once())
            ->method('normalize')
            ->willReturn($initialNormalized);

        $result = $this->normalizer->normalize($booking);

        $this->assertSame('Real Name', $result['user']['name']);
        $this->assertSame(['ROLE_TRIAL', 'ROLE_USER'], $result['user']['roles']);
        // Email is visible to trainer
        $this->assertSame('member@example.com', $result['user']['email']);
    }

    public function test_normalize_shows_real_names_and_roles_to_owner_regardless_of_settings(): void
    {
        $bookingUser = $this->createMock(User::class);
        $bookingUser->method('getId')->willReturn(42);
        $bookingUser->method('getUserIdentifier')->willReturn('member@example.com');

        $trainer = $this->createMock(User::class);
        $trainer->method('getUserIdentifier')->willReturn('trainer@example.com');

        $globalSettings = $this->createMock(GlobalSettings::class);
        $globalSettings->method('getId')->willReturn(1);
        $globalSettings->method('isWaitlistVisible')->willReturn(true);
        $globalSettings->method('isShowParticipantNames')->willReturn(false); // setting is false

        $company = $this->createMock(Company::class);
        $company->method('getGlobalSettings')->willReturn($globalSettings);
        $trainer->method('getCompany')->willReturn($company);

        $course = $this->createMock(Course::class);
        $course->method('getUser')->willReturn($trainer);

        $booking = $this->createMock(Booking::class);
        $booking->method('getCourse')->willReturn($course);
        $booking->method('getUser')->willReturn($bookingUser);
        $booking->method('isWaitlist')->willReturn(false);

        // Current user is the owner of the booking
        $this->security->method('getUser')->willReturn($bookingUser);
        $this->settingsRepository->method('find')->with(1)->willReturn($globalSettings);

        $initialNormalized = [
            'id' => 10,
            'user' => [
                'id' => 42,
                'name' => 'Real Name',
                'email' => 'member@example.com',
                'roles' => ['ROLE_TRIAL', 'ROLE_USER']
            ]
        ];

        $this->objectNormalizer->expects($this->once())
            ->method('normalize')
            ->willReturn($initialNormalized);

        $result = $this->normalizer->normalize($booking);

        $this->assertSame('Real Name', $result['user']['name']);
        $this->assertSame(['ROLE_TRIAL', 'ROLE_USER'], $result['user']['roles']);
        // Email is visible to owner
        $this->assertSame('member@example.com', $result['user']['email']);
    }

    public function test_normalize_shows_real_names_and_roles_to_other_trainer_regardless_of_settings(): void
    {
        $bookingUser = $this->createMock(User::class);
        $bookingUser->method('getId')->willReturn(42);
        $bookingUser->method('getUserIdentifier')->willReturn('member@example.com');

        $trainer = $this->createMock(User::class);
        $trainer->method('getUserIdentifier')->willReturn('trainer@example.com');

        $globalSettings = $this->createMock(GlobalSettings::class);
        $globalSettings->method('getId')->willReturn(1);
        $globalSettings->method('isWaitlistVisible')->willReturn(true);
        $globalSettings->method('isShowParticipantNames')->willReturn(false); // setting is false

        $company = $this->createMock(Company::class);
        $company->method('getGlobalSettings')->willReturn($globalSettings);
        $trainer->method('getCompany')->willReturn($company);

        $course = $this->createMock(Course::class);
        $course->method('getUser')->willReturn($trainer);

        $booking = $this->createMock(Booking::class);
        $booking->method('getCourse')->willReturn($course);
        $booking->method('getUser')->willReturn($bookingUser);
        $booking->method('isWaitlist')->willReturn(false);

        // Current user is a DIFFERENT trainer
        $otherTrainer = $this->createMock(User::class);
        $otherTrainer->method('getUserIdentifier')->willReturn('other-trainer@example.com');
        $this->security->method('getUser')->willReturn($otherTrainer);

        // Mock security.isGranted to return true for ROLE_TRAINER
        $this->security->method('isGranted')->willReturnCallback(function ($role) {
            return $role === 'ROLE_TRAINER';
        });

        $this->settingsRepository->method('find')->with(1)->willReturn($globalSettings);

        $initialNormalized = [
            'id' => 10,
            'user' => [
                'id' => 42,
                'name' => 'Real Name',
                'email' => 'member@example.com',
                'roles' => ['ROLE_TRIAL', 'ROLE_USER']
            ]
        ];

        $this->objectNormalizer->expects($this->once())
            ->method('normalize')
            ->willReturn($initialNormalized);

        $result = $this->normalizer->normalize($booking);

        $this->assertSame('Real Name', $result['user']['name']);
        $this->assertSame(['ROLE_TRIAL', 'ROLE_USER'], $result['user']['roles']);
        // Email is visible to trainer
        $this->assertSame('member@example.com', $result['user']['email']);
    }
}
