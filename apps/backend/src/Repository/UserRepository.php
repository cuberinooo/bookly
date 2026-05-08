<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * @return User[]
     */
    public function findByRole(string $role, ?Company $company = null): array
    {
        $entityManager = $this->getEntityManager();
        $filters = $entityManager->getFilters();

        // We explicitly cast roles to jsonb and the parameter to jsonb
        // This bypasses all Doctrine 'unknown type' errors
        $sql = 'SELECT u.* FROM "user" u WHERE u.roles::jsonb @> :role::jsonb';

        if ($company) {
            $sql .= ' AND u.company_id = :companyId';
        } elseif ($filters->isEnabled('company_filter')) {
            try {
                $companyId = $filters->getFilter('company_filter')->getParameter('company_id');
                if ($companyId) {
                    $sql .= ' AND u.company_id = ' . trim($companyId, "\'");
                }
            } catch (\InvalidArgumentException $e) {
                // Parameter not set, skip filtering
            }
        }

        $rsm = new \Doctrine\ORM\Query\ResultSetMappingBuilder($entityManager);
        $rsm->addRootEntityFromClassMetadata(\App\Entity\User::class, 'u');

        $query = $entityManager->createNativeQuery($sql, $rsm);

        // We pass the role as a JSON array string: ["ROLE_TRAINER"]
        $query->setParameter('role', json_encode([$role]));
        
        if ($company) {
            $query->setParameter('companyId', $company->getId());
        }

        return $query->getResult();
    }
    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
