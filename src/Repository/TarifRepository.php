<?php

namespace App\Repository;

use App\Entity\Tarif;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tarif>
 */
class TarifRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tarif::class);
    }

    public function findByServiceId(int $serviceId): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.service = :serviceId')
            ->setParameter('serviceId', $serviceId)
            ->orderBy('t.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
