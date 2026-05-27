<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Booking;
use App\Entity\Company;
use App\Entity\Course;
use App\Entity\Exercise;
use App\Entity\Meetup;
use App\Entity\User;
use App\Entity\UserWorkoutRecord;
use App\Enum\Gender;
use App\Enum\MeetupStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:load-fixtures',
    description: 'Generates development fixtures for the application.',
)]
class LoadDevelopmentFixturesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Generating Development Fixtures');

        // 1. Create/Find Company
        $company = $this->entityManager->getRepository(Company::class)->findOneBy(['name' => 'Phoenix Gym']);
        if (!$company) {
            $company = new Company();
            $company->setName('Phoenix Gym');
            $this->entityManager->persist($company);
            $io->note('Created "Phoenix Gym" company.');
        }

        // 2. Create Users
        $userData = [
            ['name' => 'Admin User', 'email' => 'admin@phoenix.test', 'roles' => ['ROLE_ADMIN'], 'gender' => Gender::MALE],
            ['name' => 'Trainer Mike', 'email' => 'trainer1@phoenix.test', 'roles' => ['ROLE_TRAINER'], 'gender' => Gender::MALE],
            ['name' => 'Trainer Sarah', 'email' => 'trainer2@phoenix.test', 'roles' => ['ROLE_TRAINER'], 'gender' => Gender::FEMALE],
            ['name' => 'Member Alice', 'email' => 'alice@phoenix.test', 'roles' => ['ROLE_MEMBER'], 'gender' => Gender::FEMALE],
            ['name' => 'Member Bob', 'email' => 'bob@phoenix.test', 'roles' => ['ROLE_MEMBER'], 'gender' => Gender::MALE],
            ['name' => 'Member Charlie', 'email' => 'charlie@phoenix.test', 'roles' => ['ROLE_MEMBER'], 'gender' => Gender::OTHER],
            ['name' => 'Trial User', 'email' => 'trial@phoenix.test', 'roles' => ['ROLE_TRIAL'], 'gender' => Gender::MALE],
        ];

        $users = [];
        foreach ($userData as $data) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
            if (!$user) {
                $user = new User();
                $user->setName($data['name']);
                $user->setEmail($data['email']);
                $user->setRoles($data['roles']);
                $user->setGender($data['gender']);
                $user->setCompany($company);
                $user->setIsVerified(true);
                $user->setIsActive(true);
                $user->setIsPublic(true);

                $cleanName = strtolower(str_replace(' ', '', $data['name']));
                $password = 'test_123'.$cleanName;
                $user->setPassword($this->passwordHasher->hashPassword($user, $password));

                $this->entityManager->persist($user);
                $io->text(sprintf('Created user: %s (Password: %s)', $data['name'], $password));
            }
            $users[] = $user;
            if ($data['roles'] === ['ROLE_TRAINER']) {
                $trainers[] = $user;
            }
            if ($data['roles'] === ['ROLE_MEMBER']) {
                $members[] = $user;
            }
        }

        $this->entityManager->flush();

        // 3. Create Exercises & Personal Bests
        $exercises = $this->entityManager->getRepository(Exercise::class)->findAll();
        if (empty($exercises)) {
            $io->warning('No exercises found. Skipping PB generation.');
        } else {
            foreach ($users as $user) {
                // Each user gets 3-5 random PBs
                $numPbs = rand(3, 5);
                shuffle($exercises);
                for ($i = 0; $i < $numPbs; ++$i) {
                    $ex = $exercises[$i];
                    $record = new UserWorkoutRecord();
                    $record->setUser($user);
                    $record->setExerciseName($ex->getName());
                    $record->setWeightValue((float) rand(40, 180));
                    $record->setDateAchieved(new \DateTime('-'.rand(1, 30).' days'));
                    $this->entityManager->persist($record);
                }
            }
            $io->note('Generated personal bests for users.');
        }

        // 4. Create Courses (Past and Future)
        $courseTitles = ['WOD', 'Strength', 'Conditioning', 'Metcon', 'Mobility'];
        $now = new \DateTime();

        // Past Courses
        for ($i = 1; $i <= 5; ++$i) {
            $course = new Course();
            $course->setTitle($courseTitles[array_rand($courseTitles)].' (Past)');
            $course->setCapacity(10);
            $start = (clone $now)->modify('-'.($i * 2).' days')->setTime(17, 0);
            $course->setStartTime($start);
            $course->setEndTime((clone $start)->modify('+1 hour'));
            $course->setCompany($company);
            $course->setUser($trainers[array_rand($trainers)]);
            $course->setAllowTrial(true);
            $this->entityManager->persist($course);

            // Add some bookings
            foreach ($members as $member) {
                if (rand(0, 1)) {
                    $booking = new Booking();
                    $booking->setUser($member);
                    $booking->setCourse($course);
                    $booking->setCompany($company);
                    $booking->setAttended((bool) rand(0, 5)); // Mostly attended
                    $this->entityManager->persist($booking);
                }
            }
        }

        // Future Courses
        for ($i = 1; $i <= 10; ++$i) {
            $course = new Course();
            $course->setTitle($courseTitles[array_rand($courseTitles)]);
            $course->setCapacity(10);
            $start = (clone $now)->modify('+'.$i.' days')->setTime(rand(7, 20), 0);
            $course->setStartTime($start);
            $course->setEndTime((clone $start)->modify('+1 hour'));
            $course->setCompany($company);
            $course->setUser($trainers[array_rand($trainers)]);
            $course->setAllowTrial(true);
            $this->entityManager->persist($course);

            // Add random bookings
            foreach ($members as $member) {
                if (rand(0, 1)) {
                    $booking = new Booking();
                    $booking->setUser($member);
                    $booking->setCourse($course);
                    $booking->setCompany($company);
                    $booking->setWaitlist(false);
                    $this->entityManager->persist($booking);
                }
            }
        }

        $io->note('Generated past and future courses with bookings.');

        // 5. Create Meetups
        $meetupTitles = ['Summer BBQ', 'Pizza Night', 'Beach Workout', 'Hiking Trip'];
        for ($i = 1; $i <= 3; ++$i) {
            $meetup = new Meetup();
            $meetup->setTitle($meetupTitles[array_rand($meetupTitles)]);
            $meetup->setDescription('Community event for all members!');
            $meetup->setLocation('Local Park');
            $start = (clone $now)->modify('+'.($i * 7).' days')->setTime(18, 0);
            $meetup->setMeetupDate($start);
            $meetup->setRsvpDeadline((clone $start)->modify('-2 days'));
            $meetup->setCompany($company);
            $meetup->setCreator($trainers[0]);
            $meetup->setStatus(MeetupStatus::OPEN);
            $this->entityManager->persist($meetup);
        }

        $io->note('Generated upcoming community meetups.');

        $this->entityManager->flush();

        $io->success('Development fixtures loaded successfully!');

        return Command::SUCCESS;
    }
}
