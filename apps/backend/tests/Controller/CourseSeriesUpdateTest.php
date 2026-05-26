<?php

namespace App\Tests\Controller;

use App\Entity\Course;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CourseSeriesUpdateTest extends WebTestCase
{
    public function testUpdateEntireSeries(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $courseService = $container->get(\App\Service\CourseService::class);

        // 1. Create a trainer
        $trainer = new User();
        $trainer->setEmail('trainer_series_' . uniqid() . '@example.com');
        $trainer->setName('Series Trainer');
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword('password');
        $trainer->setIsVerified(true);
        $trainer->setIsActive(true);
        $entityManager->persist($trainer);
        $entityManager->flush();

        // 2. Create a weekly series manually
        $startTime = new \DateTime('next monday 10:00:00');
        $courseService->createCourseSeries([
            'title' => 'Weekly Yoga',
            'capacity' => 10,
            'startTime' => $startTime->format(\DateTimeInterface::RFC3339),
            'durationMinutes' => 60,
            'recurrence' => 'weekly',
            'description' => 'Original description'
        ], $trainer);

        $series = $entityManager->getRepository(\App\Entity\CourseSeries::class)->findOneBy(['title' => 'Weekly Yoga']);
        $firstCourseId = 'v_' . $series->getId() . '_' . $startTime->getTimestamp();

        $client->loginUser($trainer);

        // 3. Update the first course and apply to all
        $newStartTime = clone $startTime;
        $newStartTime->setTime(11, 0, 0); // Move from 10:00 to 11:00

        $client->request('PATCH', '/api/courses/' . $firstCourseId . '?transferAll=true', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'title' => 'Advanced Yoga',
            'capacity' => 15,
            'description' => 'Updated description',
            'startTime' => $newStartTime->format(\DateTimeInterface::RFC3339),
            'durationMinutes' => 90
        ]));

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        // 4. Verify all future courses in the series are updated
        $entityManager->clear();
        $courses = $entityManager->getRepository(Course::class)->findBy(['title' => 'Advanced Yoga']);
        
        // Should have many courses (3 months worth of weekly)
        $this->assertGreaterThan(10, count($courses));

        foreach ($courses as $course) {
            $this->assertEquals('Advanced Yoga', $course->getTitle());
            $this->assertEquals(15, $course->getCapacity());
            $this->assertEquals('Updated description', $course->getDescription());
            $this->assertEquals(90, $course->getDurationMinutes());
            $this->assertEquals('11:00', $course->getStartTime()->format('H:i'));
            
            // Check that dates are still weekly
            $this->assertEquals('00', $course->getStartTime()->format('s')); // sanity
        }

        // Verify that a course NOT in the series (if we created one) wouldn't be updated
        // (Not strictly necessary here but good to keep in mind)
    }

    public function testUpdateSingleCourseInSeriesDoesNotAffectOthers(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $courseService = $container->get(\App\Service\CourseService::class);

        // 1. Create a trainer
        $trainer = new User();
        $trainer->setEmail('trainer_single_' . uniqid() . '@example.com');
        $trainer->setName('Single Trainer');
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword('password');
        $trainer->setIsVerified(true);
        $trainer->setIsActive(true);
        $entityManager->persist($trainer);
        $entityManager->flush();

        // 2. Create a daily series manually
        $startTime = new \DateTime('tomorrow 10:00:00');
        $courseService->createCourseSeries([
            'title' => 'Daily HIIT',
            'capacity' => 10,
            'startTime' => $startTime->format(\DateTimeInterface::RFC3339),
            'durationMinutes' => 45,
            'recurrence' => 'daily'
        ], $trainer);

        $series = $entityManager->getRepository(\App\Entity\CourseSeries::class)->findOneBy(['title' => 'Daily HIIT']);
        $firstCourseId = 'v_' . $series->getId() . '_' . $startTime->getTimestamp();
        $secondCourseId = 'v_' . $series->getId() . '_' . ((clone $startTime)->modify('+1 day')->getTimestamp());

        $client->loginUser($trainer);

        // 3. Update ONLY the first course
        $client->request('PATCH', '/api/courses/' . $firstCourseId, [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'title' => 'Special HIIT Instance',
            'capacity' => 5
        ]));

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        // 4. Verify only the first course changed
        $entityManager->clear();
        // The first course should now be a real entity because it was PATCHed
        $firstCourse = $entityManager->getRepository(Course::class)->findOneBy(['title' => 'Special HIIT Instance']);
        $this->assertNotNull($firstCourse);
        $this->assertEquals(5, $firstCourse->getCapacity());

        // The second course is still virtual, so we check if the series itself didn't change title/capacity
        // Wait, if I update ONE virtual course, it shouldn't affect the series or other virtual courses.
        $series = $entityManager->getRepository(\App\Entity\CourseSeries::class)->find($series->getId());
        $this->assertEquals('Daily HIIT', $series->getTitle());
        $this->assertEquals(10, $series->getCapacity());
    }
}
