<?php

namespace App\Tests\Controller;

use App\Entity\Course;
use App\Entity\CourseSeries;
use App\Entity\User;
use App\Enum\CourseFrequency;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CourseDeleteTest extends WebTestCase
{
    private function createTrainer($entityManager): User
    {
        $trainer = new User();
        $trainer->setEmail('trainer' . uniqid() . '@example.com');
        $trainer->setName('Trainer');
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword('password');
        $trainer->setIsVerified(true);
        $entityManager->persist($trainer);
        $entityManager->flush();
        return $trainer;
    }

    private function createSeries($entityManager, $trainer, $title = 'Test Series'): CourseSeries
    {
        $series = new CourseSeries();
        $series->setTitle($title);
        $series->setTrainer($trainer);
        $series->setScheduleStartTime(new \DateTime('+1 day'));
        $series->setDurationMinutes(60);
        $series->setCapacity(10);
        $series->setFrequency(CourseFrequency::WEEKLY);
        $entityManager->persist($series);
        $entityManager->flush();
        return $series;
    }

    public function testDeleteSingleCourse(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine.orm.entity_manager');
        $trainer = $this->createTrainer($entityManager);

        $course = new Course();
        $course->setTitle('Single Course');
        $course->setTrainer($trainer);
        $course->setStartTime(new \DateTime('+1 day'));
        $course->setEndTime(new \DateTime('+1 day 1 hour'));
        $course->setCapacity(10);
        $entityManager->persist($course);
        $entityManager->flush();

        $courseId = $course->getId();

        $client->loginUser($trainer);
        $client->request('DELETE', '/api/courses/' . $courseId);

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        
        $entityManager->clear();
        $deletedCourse = $entityManager->getRepository(Course::class)->find($courseId);
        $this->assertNull($deletedCourse);
    }

    public function testDeleteCourseSeriesReproduction(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine.orm.entity_manager');
        $trainer = $this->createTrainer($entityManager);

        $series = $this->createSeries($entityManager, $trainer, 'Series Course');
        
        // Course 1: Starts 1 minute ago (Past but should be deleted as it's the target)
        $course1 = new Course();
        $course1->setTitle('Series Course 1');
        $course1->setTrainer($trainer);
        $course1->setStartTime(new \DateTime('-1 minute'));
        $course1->setEndTime(new \DateTime('+59 minutes'));
        $course1->setCapacity(10);
        $course1->setSeries($series);
        $entityManager->persist($course1);

        // Course 2: Starts tomorrow
        $course2 = new Course();
        $course2->setTitle('Series Course 2');
        $course2->setTrainer($trainer);
        $course2->setStartTime(new \DateTime('+1 day'));
        $course2->setEndTime(new \DateTime('+1 day 1 hour'));
        $course2->setCapacity(10);
        $course2->setSeries($series);
        $entityManager->persist($course2);

        $entityManager->flush();

        $course1Id = $course1->getId();
        $course2Id = $course2->getId();
        $seriesId = $series->getId();

        $client->loginUser($trainer);
        
        // Request to delete series starting from course1
        $client->request('DELETE', '/api/courses/' . $course1Id . '?deleteAll=true');

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        
        $entityManager->clear();
        
        $deletedCourse1 = $entityManager->getRepository(Course::class)->find($course1Id);
        $deletedCourse2 = $entityManager->getRepository(Course::class)->find($course2Id);
        
        $this->assertNull($deletedCourse2, 'Future course in series should be deleted');
        $this->assertNull($deletedCourse1, 'Current/Target course in series should be deleted');
        
        $updatedSeries = $entityManager->getRepository(CourseSeries::class)->find($seriesId);
        $this->assertFalse($updatedSeries->isActive(), 'Series should be deactivated');
    }

    public function testDeleteSingleOccurrenceInSeries(): void
    {
        $client = static::createClient();
        $entityManager = $client->getContainer()->get('doctrine.orm.entity_manager');
        $trainer = $this->createTrainer($entityManager);

        $series = $this->createSeries($entityManager, $trainer, 'Single Occ');
        
        $course1 = new Course();
        $course1->setTitle('Occurrence 1');
        $course1->setTrainer($trainer);
        $course1->setStartTime(new \DateTime('+1 day'));
        $course1->setEndTime(new \DateTime('+1 day 1 hour'));
        $course1->setCapacity(10);
        $course1->setSeries($series);
        $entityManager->persist($course1);

        $course2 = new Course();
        $course2->setTitle('Occurrence 2');
        $course2->setTrainer($trainer);
        $course2->setStartTime(new \DateTime('+2 days'));
        $course2->setEndTime(new \DateTime('+2 days 1 hour'));
        $course2->setCapacity(10);
        $course2->setSeries($series);
        $entityManager->persist($course2);

        $entityManager->flush();

        $course1Id = $course1->getId();
        $course2Id = $course2->getId();
        $seriesId = $series->getId();

        $client->loginUser($trainer);
        
        // Request to delete ONLY course1
        $client->request('DELETE', '/api/courses/' . $course1Id);

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        
        $entityManager->clear();
        
        $deletedCourse1 = $entityManager->getRepository(Course::class)->find($course1Id);
        $deletedCourse2 = $entityManager->getRepository(Course::class)->find($course2Id);
        
        $this->assertNull($deletedCourse1, 'Target course should be deleted');
        $this->assertNotNull($deletedCourse2, 'Other courses in series should NOT be deleted');
        
        $updatedSeries = $entityManager->getRepository(CourseSeries::class)->find($seriesId);
        $this->assertTrue($updatedSeries->isActive(), 'Series should still be active when only one instance is deleted');
    }
}
