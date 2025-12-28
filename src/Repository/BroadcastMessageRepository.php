<?php

namespace App\Repository;

use App\Entity\BroadcastMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BroadcastMessage>
 */
class BroadcastMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BroadcastMessage::class);
    }

    /**
     * @return BroadcastMessage[]
     */
    public function findActiveMessages(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.active = :active')
            ->setParameter('active', true)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
