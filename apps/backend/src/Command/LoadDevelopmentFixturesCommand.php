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
use App\Enum\RsvpStatus;
use App\Entity\MeetupRsvp;
use App\Entity\MeetupComment;
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
            ['name' => 'Member David', 'email' => 'david@phoenix.test', 'roles' => ['ROLE_MEMBER'], 'gender' => Gender::MALE],
            ['name' => 'Member Emma', 'email' => 'emma@phoenix.test', 'roles' => ['ROLE_MEMBER'], 'gender' => Gender::FEMALE],
            ['name' => 'Member Frank', 'email' => 'frank@phoenix.test', 'roles' => ['ROLE_MEMBER'], 'gender' => Gender::MALE],
            ['name' => 'Member Grace', 'email' => 'grace@phoenix.test', 'roles' => ['ROLE_MEMBER'], 'gender' => Gender::FEMALE],
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
                $isAlice = $member->getEmail() === 'alice@phoenix.test';
                // Guarantee Alice is booked on the first 3 future courses, others are random
                if ($isAlice ? ($i <= 3) : (rand(0, 1) === 1)) {
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

        // 6. Create a Mega Meetup with many participants and a long description
        $megaMeetup = new Meetup();
        $megaMeetup->setTitle('Community Mega Meetup & Games 2026');
        $megaMeetup->setDescription("Join us for the biggest community event of the year! We will have multiple stations with different games, a huge BBQ with options for everyone, and a live DJ to keep the energy high.

It's a great opportunity to meet fellow athletes from all squads, share some training tips, and just have a blast outside the gym. We recommend bringing comfortable clothes, sunscreen, and a lot of good vibes!

Schedule:
14:00 - Arrival & Welcome Drinks
15:00 - Community Games (Team Challenges)
17:00 - BBQ & Refreshments
19:00 - Awards Ceremony & Socializing
21:00 - Event Wrap-up

Don't miss out on this legendary day. Please make sure to RSVP by the deadline so we can plan the catering accordingly. We can't wait to see everyone there!");
        $megaMeetup->setLocation('Olympic Stadium Park, North Entrance');
        $start = (clone $now)->modify('+14 days')->setTime(14, 0);
        $megaMeetup->setMeetupDate($start);
        $megaMeetup->setRsvpDeadline((clone $start)->modify('-3 days'));
        $megaMeetup->setCompany($company);
        $megaMeetup->setCreator($trainers[1]);
        $megaMeetup->setStatus(MeetupStatus::OPEN);
        $megaMeetup->setMaxParticipants(50);
        $this->entityManager->persist($megaMeetup);

        // Add many RSVPs to the Mega Meetup
        foreach ($users as $user) {
            $rsvp = new MeetupRsvp();
            $rsvp->setMeetup($megaMeetup);
            $rsvp->setUser($user);
            $rsvp->setCompany($company);
            $rsvp->setStatus(RsvpStatus::GOING);
            $this->entityManager->persist($rsvp);
        }

        // Add some comments to the Mega Meetup
        $commentAuthors = [$users[0], $users[3], $users[4], $users[1]];
        $commentTexts = [
            "I'm so excited for this! Should we bring some extra drinks?",
            "Can't wait for the team challenges! Team Alice will win!",
            "I'll bring some vegan burgers for the BBQ. Who else wants some?",
            "Great idea Emma! I'll bring some fresh salads too."
        ];

        foreach ($commentTexts as $index => $text) {
            $comment = new MeetupComment();
            $comment->setMeetup($megaMeetup);
            $comment->setAuthor($commentAuthors[$index]);
            $comment->setContent($text);
            $comment->setCompany($company);
            $comment->setCreatedAt((new \DateTimeImmutable())->modify('-'.(5 - $index).' hours'));
            $this->entityManager->persist($comment);
        }

        // 7. Create a Planning Phase Meetup (No dates)
        $planningMeetup = new Meetup();
        $planningMeetup->setTitle('Winter Ski Trip Planning');
        $planningMeetup->setDescription('We are planning a ski trip for early 2027. This meetup is for discussing potential locations, dates, and sharing costs. No fixed date yet, let\'s use the comments to coordinate!');
        $planningMeetup->setLocation('TBD');
        $planningMeetup->setCompany($company);
        $planningMeetup->setCreator($trainers[0]);
        $planningMeetup->setStatus(MeetupStatus::OPEN);
        $this->entityManager->persist($planningMeetup);

        $io->note('Generated upcoming community meetups, a Mega Meetup with comments, and a planning phase meetup.');

        $this->entityManager->flush();

        $io->success('Development fixtures loaded successfully!');

        return Command::SUCCESS;
    }
}
