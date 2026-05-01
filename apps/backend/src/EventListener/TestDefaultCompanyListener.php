<?php

namespace App\EventListener;

use App\Entity\Company;
use App\Entity\CompanyAwareInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class TestDefaultCompanyListener
{
    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof CompanyAwareInterface) {
            return;
        }

        if ($entity->getCompany() !== null) {
            return;
        }

        $entityManager = $args->getObjectManager();
        $companyRepository = $entityManager->getRepository(Company::class);
        
        // Find or create a default company for tests
        $company = $companyRepository->findOneBy(['name' => 'Test Default Company']);
        if (!$company) {
            $company = new Company();
            $company->setName('Test Default Company');
            $entityManager->persist($company);
            // We don't flush here as it's prePersist
        }

        $entity->setCompany($company);
    }
}
