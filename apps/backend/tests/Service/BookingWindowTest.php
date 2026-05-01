<?php

namespace App\Tests\Service;

use App\Entity\Course;
use App\Entity\GlobalSettings;
use App\Entity\User;
use App\Enum\BookingWindow;
use App\Repository\BookingRepository;
use App\Repository\GlobalSettingsRepository;
use App\Service\BookingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;

class BookingWindowTest extends TestCase
{
    private $entityManager;
    private $bookingRepository;
    private $settingsRepository;
    private $mailer;
    private $bookingService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->bookingRepository = $this->createMock(BookingRepository::class);
        $this->settingsRepository = $this->getMockBuilder(GlobalSettingsRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->mailer = $this->createMock(MailerInterface::class);

        $this->bookingService = new BookingService(
            $this->entityManager,
            $this->bookingRepository,
            $this->settingsRepository,
            $this->mailer
        );
    }

    public function testBookOutsideCurrentWeekWindow(): void
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
        $daysToMonday = $day === 0 ? 1 : 8 - $day;
        $nextMonday->modify("+$daysToMonday days");
        $nextMonday->setTime(10, 0);

        $course = new Course();
        $course->setTitle('Next Week Course');
        $course->setUser($trainer);
        $course->setStartTime($nextMonday);
        $course->setEndTime((clone $nextMonday)->modify('+1 hour'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Bookings are only allowed for the current week.');

        $this->bookingService->book($course, $user);
    }

    public function testBookWithinCurrentWeekWindow(): void
    {
        $trainer = $this->createMock(User::class);
        $trainer->method('getId')->willReturn(1);
        $trainer->method('getName')->willReturn('Trainer');

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(2);
        $user->method('getName')->willReturn('Member');

        $company = new \App\Entity\Company();
        $globalSettings = new GlobalSettings();
        $company->setGlobalSettings($globalSettings);

        $trainer->method('getCompany')->willReturn($company);

        $settings = new GlobalSettings();
        $settings->setBookingWindow(BookingWindow::CURRENT_WEEK);

        $this->settingsRepository->method('find')->willReturn($settings);

        // Course starts this Friday (or Sunday if today is Friday/Saturday)
        $courseDate = new \DateTime();
        $day = (int) $courseDate->format('w');
        if ($day === 0) {
            // Already Sunday, keep today
        } else {
            $daysToSunday = 7 - $day;
            $courseDate->modify("+$daysToSunday days");
        }
        $courseDate->setTime(10, 0);

        $course = new Course();
        $course->setTitle('This Week Course');
        $course->setUser($trainer);
        $course->setStartTime($courseDate);
        $course->setEndTime((clone $courseDate)->modify('+1 hour'));
        $course->setCapacity(10);

        $this->bookingRepository->method('findOneBy')->willReturn(null);
        $this->bookingRepository->method('count')->willReturn(0);

        // Should NOT throw exception
        $this->bookingService->book($course, $user);

        $this->assertTrue(true);
    }
}
