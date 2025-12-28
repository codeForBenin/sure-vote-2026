<?php

namespace App\Repository;

use App\Entity\Resultat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ResultatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Resultat::class);
    }

    public function getSommeVoixParBureau(string $bureauId): int
    {
        $result = $this->createQueryBuilder('r')
            ->select('SUM(r.nombreVoix) as total')
            ->where('r.bureauDeVote = :bureauId')
            ->setParameter('bureauId', $bureauId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }
}
