<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
    public function findSupervisorsByDepartement(string $departement): array
    {
        // On récupère tous les utilisateurs du département (filtre SQL simple)
        $users = $this->createQueryBuilder('u')
            ->andWhere('u.departement = :departement')
            ->setParameter('departement', $departement)
            ->getQuery()
            ->getResult();

        // On filtre en PHP pour éviter l'erreur SQL sur le champ JSON (roles)
        return array_filter($users, function (User $user) {
            return in_array('ROLE_SUPERVISEUR', $user->getRoles());
        });
    }

    public function findAdmins(): array
    {
        // On récupère tous les utilisateurs
        // Note: Si la base devient très grande, il faudra trouver une autre stratégie (ex: table séparée ou NativeQuery)
        $users = $this->findAll();

        return array_filter($users, function (User $user) {
            return in_array('ROLE_ADMIN', $user->getRoles()) || in_array('ROLE_SUPER_ADMIN', $user->getRoles());
        });
    }
}
