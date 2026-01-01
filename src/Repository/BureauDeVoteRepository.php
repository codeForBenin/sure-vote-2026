<?php

namespace App\Repository;

use App\Entity\BureauDeVote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BureauDeVoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BureauDeVote::class);
    }

    public function getTotalInscrits(): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('SUM(b.nombreInscrits)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function search(string $query)
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.centre', 'c')
            ->leftJoin('c.circonscription', 'ci')
            ->where('b.nom LIKE :query')
            ->orWhere('b.code LIKE :query')
            ->orWhere('c.nom LIKE :query')
            ->orWhere('c.adresse LIKE :query')
            ->orWhere('ci.nom LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }
}
