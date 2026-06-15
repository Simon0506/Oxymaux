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

    public function findReservationsForReminder(): array
    {
        $now = new \DateTime('now', new \DateTimeZone('Europe/Paris')); // Assure-toi d'utiliser le bon fuseau horaire

        // Fenêtre cible de 48h (on prend une marge entre 47h et 49h pour ne rater aucune séance)
        $targetMin = (clone $now)->modify('+47 hours');
        $targetMax = (clone $now)->modify('+49 hours');

        $dateMin = $targetMin->format('Y-m-d');
        $startMin = $targetMin->format('H:i:s');

        $dateMax = $targetMax->format('Y-m-d');
        $startMax = $targetMax->format('H:i:s');

        $qb = $this->createQueryBuilder('r')
            ->join('r.activity', 'a') // Jointure avec l'activité qui porte la date et l'heure
            ->andWhere('r.status = :status')
            ->andWhere('r.reminderSent = :reminderSent');

        // Gestion du chevauchement si la fenêtre s'étale sur deux jours différents
        if ($dateMin === $dateMax) {
            $qb->andWhere('a.date = :dateMin')
                ->andWhere('a.start BETWEEN :startMin AND :startMax')
                ->setParameter('dateMin', $dateMin)
                ->setParameter('startMin', $startMin)
                ->setParameter('startMax', $startMax);
        } else {
            $qb->andWhere(
                $qb->expr()->orX(
                    '(a.date = :dateMin AND a.start >= :startMin)',
                    '(a.date = :dateMax AND a.start <= :startMax)'
                )
            )
                ->setParameter('dateMin', $dateMin)
                ->setParameter('dateMax', $dateMax)
                ->setParameter('startMin', $startMin)
                ->setParameter('startMax', $startMax);
        }

        return $qb->setParameter('status', Reservation::STATUS_VALIDATED) // À adapter selon tes valeurs de statut
            ->setParameter('reminderSent', false)
            ->getQuery()
            ->getResult();
    }
}
