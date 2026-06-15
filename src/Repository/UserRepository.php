<?php

namespace App\Repository;

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

    public function findAdmin()
    {
        return $this->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%"ROLE_ADMIN"%')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findUsersInactiveSince(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.lastLoginAt < :date')
            ->andWhere('u.roles NOT LIKE :adminRole')
            ->setParameter('adminRole', '%"ROLE_ADMIN"%')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    public function findUsersToWarnBeforeDeletion(\DateTimeInterface $startWindow, \DateTimeInterface $endWindow): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.lastLoginAt >= :startWindow AND u.lastLoginAt < :endWindow')
            ->andWhere('u.warnedForDeletionAt IS NULL') // On ne veut que ceux qui n'ont pas encore été avertis
            ->andWhere('u.roles NOT LIKE :adminRole')
            ->setParameter('startWindow', $startWindow) // Ex: Il y a 3 ans
            ->setParameter('endWindow', $endWindow)     // Ex: Il y a 2 ans et 11 mois (soit 1 mois avant la suppression)
            ->setParameter('adminRole', '%"ROLE_ADMIN"%')
            ->getQuery()
            ->getResult();
    }
}
