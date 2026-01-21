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
            ->where('LOWER(b.nom) LIKE :query')
            ->orWhere('LOWER(b.code) LIKE :query')
            ->orWhere('LOWER(c.nom) LIKE :query')
            ->orWhere('LOWER(c.commune) LIKE :query')
            ->orWhere('LOWER(c.arrondissement) LIKE :query')
            ->orWhere('LOWER(c.villageQuartier) LIKE :query')
            ->orWhere('LOWER(c.departement) LIKE :query')
            ->orWhere('LOWER(ci.nom) LIKE :query')
            ->setParameter('query', '%' . strtolower($query) . '%')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }
}
