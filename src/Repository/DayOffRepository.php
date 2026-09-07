<?php

namespace App\Repository;

use App\Entity\DayOff;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DayOff>
 */
class DayOffRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DayOff::class);
    }

    public function findByMonth(string $month): array
    {
        $startDate = new \DateTime($month . '-01');
        $endDate = (clone $startDate)->modify('last day of this month');

        return $this->createQueryBuilder('d')
            ->where('d.date BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getResult();
    }
}
