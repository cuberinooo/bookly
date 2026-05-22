<?php

namespace App\Tests\Service;

use App\Entity\Exercise;
use App\Entity\User;
use App\Entity\UserWorkoutRecord;
use App\Repository\UserRepository;
use App\Repository\UserWorkoutRecordRepository;
use App\Service\LeaderboardService;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class LeaderboardServiceTest extends TestCase
{
    private $entityManager;
    private $userRepository;
    private $recordRepository;
    private $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->recordRepository = $this->createMock(UserWorkoutRecordRepository::class);
        
        $this->service = new LeaderboardService(
            $this->entityManager,
            $this->userRepository,
            $this->recordRepository
        );
    }

    public function testGetMonthlyStats(): void
    {
        // Mock for attendance query
        $attendanceQb = $this->createMock(QueryBuilder::class);
        $attendanceQuery = $this->createMock(Query::class);
        
        $attendanceQb->method('select')->willReturnSelf();
        $attendanceQb->method('from')->willReturnSelf();
        $attendanceQb->method('leftJoin')->willReturnSelf();
        $attendanceQb->method('andWhere')->willReturnSelf();
        $attendanceQb->method('setParameter')->willReturnSelf();
        $attendanceQb->method('groupBy')->willReturnSelf();
        $attendanceQb->method('getQuery')->willReturn($attendanceQuery);
        $attendanceQuery->method('getArrayResult')->willReturn([
            ['userId' => 1, 'attendanceCount' => 5],
            ['userId' => 2, 'attendanceCount' => 3]
        ]);

        // Mock for streak query
        $streakQb = $this->createMock(QueryBuilder::class);
        $streakQuery = $this->createMock(Query::class);
        
        $streakQb->method('select')->willReturnSelf();
        $streakQb->method('from')->willReturnSelf();
        $streakQb->method('join')->willReturnSelf();
        $streakQb->method('andWhere')->willReturnSelf();
        $streakQb->method('setParameter')->willReturnSelf();
        $streakQb->method('orderBy')->willReturnSelf();
        $streakQb->method('addOrderBy')->willReturnSelf();
        $streakQb->method('getQuery')->willReturn($streakQuery);
        $streakQuery->method('getArrayResult')->willReturn([]);

        $this->entityManager->method('createQueryBuilder')->willReturnOnConsecutiveCalls($attendanceQb, $streakQb);
        
        $user1 = $this->createMock(User::class);
        $user1->method('getId')->willReturn(1);
        $user1->method('getName')->willReturn('Athlete A');
        $user1->method('getGender')->willReturn(\App\Enum\Gender::MALE);
        
        $user2 = $this->createMock(User::class);
        $user2->method('getId')->willReturn(2);
        $user2->method('getName')->willReturn('Athlete B');
        $user2->method('getGender')->willReturn(\App\Enum\Gender::FEMALE);

        $this->userRepository->method('findBy')->with(['isActive' => true, 'isPublic' => true])->willReturn([$user1, $user2]);

        $stats = $this->service->getMonthlyStats();

        $this->assertCount(2, $stats);
        $this->assertEquals('Athlete A', $stats[0]['name']);
        $this->assertEquals(5, $stats[0]['attendanceCount']);
        $this->assertEquals('male', $stats[0]['gender']);
    }

    public function testGetWorkoutRecords(): void
    {
        $this->recordRepository->method('findTopRecordsByExercise')->willReturn([
            [
                'exerciseName' => 'Deadlift',
                'userId' => 1,
                'userName' => 'John',
                'profilePicture' => 'john.jpg',
                'gender' => 'male',
                'maxWeight' => 200.0,
                'dateAchieved' => new \DateTime()
            ]
        ]);

        $exercise = new Exercise();
        $exercise->setName('Deadlift');
        $exercise->setUnit('kg');
        
        $exerciseRepo = $this->createMock(EntityRepository::class);
        $this->entityManager->method('getRepository')->with(Exercise::class)->willReturn($exerciseRepo);
        $exerciseRepo->method('findAll')->willReturn([$exercise]);

        $records = $this->service->getWorkoutRecords();

        $this->assertArrayHasKey('Deadlift', $records);
        $this->assertArrayHasKey('male', $records['Deadlift']);
        $this->assertCount(1, $records['Deadlift']['male']);
        $this->assertEquals(200.0, $records['Deadlift']['male'][0]['weightValue']);
    }

    public function testSubmitRecord(): void
    {
        $user = new User();
        $exerciseName = 'Snatch';
        $weight = 100.0;

        $exercise = new Exercise();
        $exercise->setName($exerciseName);
        
        $exerciseRepo = $this->createMock(EntityRepository::class);
        $this->entityManager->method('getRepository')->with(Exercise::class)->willReturn($exerciseRepo);
        $exerciseRepo->method('findOneBy')->with(['name' => $exerciseName])->willReturn($exercise);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $record = $this->service->submitRecord($user, $exerciseName, $weight);

        $this->assertInstanceOf(UserWorkoutRecord::class, $record);
        $this->assertEquals($exerciseName, $record->getExerciseName());
        $this->assertEquals($weight, $record->getWeightValue());
        $this->assertSame($user, $record->getUser());
    }

    public function testSubmitInvalidExerciseThrowsException(): void
    {
        $user = new User();
        
        $exerciseRepo = $this->createMock(EntityRepository::class);
        $this->entityManager->method('getRepository')->with(Exercise::class)->willReturn($exerciseRepo);
        $exerciseRepo->method('findOneBy')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid exercise');

        $this->service->submitRecord($user, 'NonExistent', 50.0);
    }
}
