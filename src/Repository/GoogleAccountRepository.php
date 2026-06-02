<?php

namespace App\Repository;

use App\Entity\GoogleAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GoogleAccount>
 */
class GoogleAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoogleAccount::class);
    }

    public function findAdminGoogleAccount(): ?GoogleAccount
    {
        return $this->createQueryBuilder('g')
            ->join('g.admin', 'u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
