<?php

namespace App\Repository;

use App\Entity\Participation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Participation>
 */
class ParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participation::class);
    }

    public function findLatestByBureau($bureauId): ?Participation
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.bureauDeVote = :bureauId')
            ->setParameter('bureauId', $bureauId)
            ->orderBy('p.heurePointage', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getGlobalVotantsEstimate(): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT SUM(b_max.votants_estimes) as total
            FROM (
                SELECT 
                    b.id,
                    GREATEST(
                        COALESCE((SELECT MAX(p.nombre_votants) FROM participation p WHERE p.bureau_de_vote_id = b.id), 0),
                        COALESCE((SELECT SUM(r.nombre_voix) FROM resultat r WHERE r.bureau_de_vote_id = b.id), 0)
                    ) as votants_estimes
                FROM bureau_de_vote b
            ) as b_max
        ';

        try {
            $result = $conn->executeQuery($sql)->fetchOne();
            return (int) $result;
        } catch (\Exception $e) {
            return 0;
        }
    }

}
