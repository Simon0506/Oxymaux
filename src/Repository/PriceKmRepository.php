<?php

namespace App\Repository;

use App\Entity\PriceKm;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PriceKm>
 */
class PriceKmRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PriceKm::class);
    }

    public function sortByLength(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.minLength', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
