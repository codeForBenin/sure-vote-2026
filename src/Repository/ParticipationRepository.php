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

    public function getVotesCountAtTime(\DateTimeInterface $time): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT SUM(p.nombre_votants)
            FROM participation p
            INNER JOIN (
                SELECT p2.bureau_de_vote_id, MAX(p2.heure_pointage) as max_time
                FROM participation p2
                WHERE p2.heure_pointage <= :time
                GROUP BY p2.bureau_de_vote_id
            ) latest_p ON p.bureau_de_vote_id = latest_p.bureau_de_vote_id AND p.heure_pointage = latest_p.max_time
        ';

        try {
            $result = $conn->executeQuery($sql, ['time' => $time->format('Y-m-d H:i:s')])->fetchOne();
            return (int) $result;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
