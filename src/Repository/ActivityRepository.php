<?php

namespace App\Repository;

use App\Entity\Activity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activity>
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    public function findByMonth(string $month): array
    {
        $currentMonth = \DateTime::createFromFormat('Y-m', $month);
        $startOfMonth = (clone $currentMonth)->modify('first day of this month')->setTime(0, 0, 0);
        $endOfMonth = (clone $currentMonth)->modify('last day of this month')->setTime(23, 59, 59);

        return $this->createQueryBuilder('a')
            ->andWhere('a.date >= :start')
            ->andWhere('a.date <= :end')
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->orderBy('a.date', 'ASC')
            ->addOrderBy('a.start', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findUpcomingActivityByService(string $service): ?Activity
    {
        $now = new \DateTime();
        return $this->createQueryBuilder('a')
            ->join('a.service', 's')
            ->andWhere('s.id = :serviceId')
            ->andWhere('a.date >= :now')
            ->setParameter('serviceId', $service)
            ->setParameter('now', $now)
            ->orderBy('a.date', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
