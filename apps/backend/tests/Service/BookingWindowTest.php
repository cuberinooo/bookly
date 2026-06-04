<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Course;
use App\Entity\GlobalSettings;
use App\Entity\User;
use App\Enum\BookingWindow;
use App\Repository\BookingRepository;
use App\Repository\GlobalSettingsRepository;
use App\Service\BookingService;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class BookingWindowTest extends TestCase
{
    private $entityManager;
    private $bookingRepository;
    private $settingsRepository;
    private $emailService;
    private $translator;
    private $bookingService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->bookingRepository = $this->createMock(BookingRepository::class);
        $this->settingsRepository = $this->getMockBuilder(GlobalSettingsRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->emailService = $this->createMock(EmailService::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);

        $this->bookingService = new BookingService(
            $this->entityManager,
            $this->bookingRepository,
            $this->settingsRepository,
            $this->translator,
            $this->emailService
        );
    }

    public function test_book_outside_current_week_window(): void
    {
        $trainer = new User();
        $trainer->setName('Trainer');

        $company = new \App\Entity\Company();
        $globalSettings = new GlobalSettings();
        $company->setGlobalSettings($globalSettings);
        $trainer->setCompany($company);

        $user = new User();
        $user->setName('Member');

        $settings = new GlobalSettings();
        $settings->setBookingWindow(BookingWindow::CURRENT_WEEK);

        $this->settingsRepository->method('find')->willReturn($settings);

        // Course starts next Monday
        $nextMonday = new \DateTime();
        $day = (int) $nextMonday->format('w');
        $daysToMonday = 0 === $day ? 1 : 8 - $day;
        $nextMonday->modify("+$daysToMonday days");
        $nextMonday->setTime(10, 0);

        $course = new Course();
        $course->setTitle('Next Week Course');
        $course->setUser($trainer);
        $course->setStartTime($nextMonday);
        $course->setEndTime((clone $nextMonday)->modify('+1 hour'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('error.booking_window_current_week');

        $this->bookingService->book($course, $user);
    }

    public function test_book_within_current_week_window(): void
    {
        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(1);
        $trainer->method('getName')->willReturn('Trainer');

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(2);
        $user->method('getName')->willReturn('Member');
        $user->method('getEmail')->willReturn('member@example.com');
        $user->method('getRoles')->willReturn(['ROLE_MEMBER']);

        $company = new \App\Entity\Company();
        $globalSettings = new GlobalSettings();
        $company->setGlobalSettings($globalSettings);
        $user->method('getCompany')->willReturn($company);

        $trainer->method('getCompany')->willReturn($company);

        $settings = new GlobalSettings();
        $settings->setBookingWindow(BookingWindow::CURRENT_WEEK);

        $this->settingsRepository->method('find')->willReturn($settings);

        $courseDate = new \DateTime();
        $day = (int) $courseDate->format('w');
        $daysToSunday = 0 === $day ? 0 : 7 - $day;
        $courseDate->modify("+$daysToSunday days");
        $courseDate->setTime(23, 0);

        if ($courseDate <= new \DateTime()) {
            $courseDate->modify('+1 hour');
        }

        $course = $this->createMock(Course::class);
        $course->method('getId')->willReturn(10);
        $course->method('getTitle')->willReturn('This Week Course');
        $course->method('getUser')->willReturn($trainer);
        $course->method('getStartTime')->willReturn($courseDate);
        $course->method('getEndTime')->willReturn((clone $courseDate)->modify('+1 hour'));
        $course->method('getCapacity')->willReturn(10);
        $course->method('getCompany')->willReturn($company);
        $course->method('getStatus')->willReturn(\App\Enum\CourseStatus::ACTIVE);

        $this->bookingRepository->method('findOneBy')->willReturn(null);
        $this->bookingRepository->method('count')->willReturn(0);

        $this->bookingService->book($course, $user);

        $this->assertTrue(true);
    }
}
