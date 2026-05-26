<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\CycleAssignment;
use App\Entity\TrainingCategory;
use App\Entity\TrainingCycle;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CategoryDeletionCascadeTest extends WebTestCase
{
    private function createCompany(\Doctrine\ORM\EntityManagerInterface $em, string $name): Company
    {
        $company = new Company();
        $company->setName($name);
        $em->persist($company);

        return $company;
    }

    public function test_delete_category_cascades_assignments(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $company = $this->createCompany($entityManager, 'Fitness Club '.uniqid());

        // 1. Create Trainer A
        $trainer = new User();
        $trainer->setEmail('trainera'.uniqid().'@example.com');
        $trainer->setName('Trainer A');
        $trainer->setRoles(['ROLE_TRAINER']);
        $trainer->setPassword('password');
        $trainer->setIsVerified(true);
        $trainer->setCompany($company);
        $entityManager->persist($trainer);

        // 2. Create Training Category
        $category = new TrainingCategory();
        $category->setCompany($company);
        $category->setName('Cardio Burn');
        $category->setColorHex('#e74c3c');
        $entityManager->persist($category);

        // 3. Create Training Cycle starting today
        $cycle = new TrainingCycle();
        $cycle->setCompany($company);
        $cycle->setName('Summer Shred');
        $cycle->setStartDate(new \DateTime('today'));
        $cycle->setDurationWeeks(4);
        $cycle->setIsActive(true);

        // Assignment for today (Week 1, Day of week)
        $dayOfWeek = (int) (new \DateTime('today'))->format('N');
        $assignment = new CycleAssignment();
        $assignment->setWeekNumber(1);
        $assignment->setDayOfWeek($dayOfWeek);
        $assignment->setCategory($category);
        $cycle->addAssignment($assignment);

        $entityManager->persist($cycle);
        $entityManager->persist($assignment);
        $entityManager->flush();

        $categoryId = $category->getId();
        $assignmentId = $assignment->getId();

        // Verify they are persisted
        $this->assertNotNull($categoryId);
        $this->assertNotNull($assignmentId);

        // 4. Log in as Trainer and call delete category endpoint
        $client->loginUser($trainer);
        $client->request('DELETE', '/api/training-cycles/categories/'.$categoryId);

        // Assert response is NO_CONTENT
        $this->assertEquals(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        // Clear entity manager to get fresh database state
        $entityManager->clear();

        // 5. Assert category is deleted
        $deletedCategory = $entityManager->getRepository(TrainingCategory::class)->find($categoryId);
        $this->assertNull($deletedCategory, 'Category should be deleted from the database');

        // 6. Assert assignment is deleted automatically by CASCADE
        $deletedAssignment = $entityManager->getRepository(CycleAssignment::class)->find($assignmentId);
        $this->assertNull($deletedAssignment, 'CycleAssignment referencing deleted Category should be deleted via CASCADE');
    }
}
