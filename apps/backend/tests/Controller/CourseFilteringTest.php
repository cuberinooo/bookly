<?php

namespace App\Tests\Controller;

use App\Entity\Course;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CourseFilteringTest extends WebTestCase
{
    public function testFutureOnlyFiltering(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        // Create a trainer
        $trainer = new User();
        $trainer->setEmail('trainer_filter_' . uniqid() . '@example.com');
        $trainer->setName('Filter Trainer');
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword('password');
        $trainer->setIsVerified(true);
        $entityManager->persist($trainer);

        // Create a past course
        $pastCourse = new Course();
        $pastCourse->setTitle('Past Course');
        $pastCourse->setTrainer($trainer);
        $pastCourse->setStartTime(new \DateTime('-2 days'));
        $pastCourse->setEndTime(new \DateTime('-2 days 1 hour'));
        $pastCourse->setCapacity(10);
        $entityManager->persist($pastCourse);

        // Create a future course
        $futureCourse = new Course();
        $futureCourse->setTitle('Future Course');
        $futureCourse->setTrainer($trainer);
        $futureCourse->setStartTime(new \DateTime('+2 days'));
        $futureCourse->setEndTime(new \DateTime('+2 days 1 hour'));
        $futureCourse->setCapacity(10);
        $entityManager->persist($futureCourse);

        $entityManager->flush();

        // 1. Test all courses
        $client->request('GET', '/api/courses?all=true');
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        
        // At least our two courses should be there (other tests might have created more)
        $titles = array_column($data, 'title');
        $this->assertContains('Past Course', $titles);
        $this->assertContains('Future Course', $titles);

        // 2. Test futureOnly=true with all=true
        $client->request('GET', '/api/courses?all=true&futureOnly=true');
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        $titles = array_column($data, 'title');
        
        $this->assertNotContains('Past Course', $titles);
        $this->assertContains('Future Course', $titles);

        // 3. Test futureOnly=true with pagination
        $client->request('GET', '/api/courses?futureOnly=true');
        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        $titles = array_column($data['data'], 'title');
        
        $this->assertNotContains('Past Course', $titles);
        $this->assertContains('Future Course', $titles);
    }
}
