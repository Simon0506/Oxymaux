<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function findByUserId(int $userId)
    {
        return $this->createQueryBuilder('r')
            ->join('r.activity', 'a')
            ->join('a.service', 's')
            ->join('r.dog', 'd')
            ->andWhere('d.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }

    public function findByActivitySortedByStatus(int $activityId)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.activity = :activityId')
            ->setParameter('activityId', $activityId)
            ->orderBy("CASE r.status 
                WHEN 'validée' THEN 1 
                WHEN 'en attente' THEN 2 
                WHEN 'refusée' THEN 3 
                ELSE 4 END", 'ASC')
            ->getQuery()
            ->getResult();
    }
}
