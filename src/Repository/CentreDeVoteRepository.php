<?php

namespace App\Repository;

use App\Entity\CentreDeVote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CentreDeVoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CentreDeVote::class);
    }
    public function search(string $query)
    {
        // 1. Normalize query exactly like the data
        $normalizedQuery = \transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $query);
        if ($normalizedQuery === false) {
            $normalizedQuery = strtolower($query);
        }
        $normalizedQuery = preg_replace('/[^a-z0-9]/', ' ', $normalizedQuery);
        $normalizedQuery = trim(preg_replace('/\s+/', ' ', $normalizedQuery));

        // 2. Multi-word search (AND logic)
        // Checks if ALL words in the query appear in the searchContent
        $words = explode(' ', $normalizedQuery);
        
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.nom', 'ASC')
            ->setMaxResults(50);
            
        $andExpr = $qb->expr()->andX();
        
        foreach ($words as $i => $word) {
            if (strlen($word) > 1) { // Skip 1-letter words to avoid noise
                $andExpr->add($qb->expr()->like('c.searchContent', ":word_$i"));
                $qb->setParameter("word_$i", '%' . $word . '%');
            }
        }
        
        if ($andExpr->count() > 0) {
            $qb->where($andExpr);
        } else {
             // Fallback if query was empty after cleaning
             return [];
        }

        return $qb->getQuery()->getResult();
    }
}
