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

    public function countBureauxAvecResultats(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.bureauDeVote)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les résultats agrégés par parti pour une circonscription donnée
     */
    public function findResultatsParCirconscription(string $circonscriptionId): array
    {
        return $this->createQueryBuilder('r')
            ->select('p.id as parti_id', 'p.nom as parti_nom', 'p.sigle as parti_sigle', 'p.couleur as parti_couleur', 'p.affiliation as parti_affiliation', 'SUM(r.nombreVoix) as total_voix')
            ->join('r.parti', 'p')
            ->join('r.bureauDeVote', 'b')
            ->join('b.centre', 'c')
            ->join('c.circonscription', 'circo')
            ->where('circo.id = :circoId')
            ->setParameter('circoId', $circonscriptionId)
            ->groupBy('p.id')
            ->orderBy('total_voix', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getTotalVoix(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('SUM(r.nombreVoix)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatest(int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.bureauDeVote', 'b')
            ->addSelect('b')
            ->leftJoin('r.parti', 'p')
            ->addSelect('p')
            ->orderBy('r.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
