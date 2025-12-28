<?php

namespace App\Repository;

use App\Entity\Logs;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Logs>
 */
class LogsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Logs::class);
    }

    /**
     * Helper pour logger rapidement une action
     */
    public function logAction(string $action, ?User $user = null, ?string $ip = null, ?string $userAgent = null, ?array $details = []): Logs
    {
        $log = new Logs();
        $log->setAction($action);
        $log->setUser($user);
        $log->setIpAddress($ip);
        $log->setUserAgent($userAgent); // Le "média" utilisé
        $log->setDetails($details);

        $this->getEntityManager()->persist($log);
        $this->getEntityManager()->flush();

        return $log;
    }
}
