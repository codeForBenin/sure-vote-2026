<?php

namespace App\Repository;

use App\Entity\Observation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Observation>
 */
class ObservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Observation::class);
    }

    /**
     * @return Observation[]
     */
    public function findByBureau(string $bureauId): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.bureauDeVote = :bureauId')
            ->setParameter('bureauId', $bureauId)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Observation[]
     */
    public function findByCentre(string $centreId): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.centreDeVote = :centreId')
            ->setParameter('centreId', $centreId)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
