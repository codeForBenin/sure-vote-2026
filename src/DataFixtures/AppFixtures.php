<?php

namespace App\DataFixtures;

use App\Entity\BureauDeVote;
use App\Entity\CentreDeVote;
use App\Entity\Circonscription;
use App\Entity\Election;
use App\Entity\Parti;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // 0. Election
        $election = new Election();
        $election->setNom('Législatives 2026');
        $election->setDateElection(new \DateTimeImmutable('2026-01-08'));
        $election->setIsActive(true);
        $manager->persist($election);

        // 1. Partis Politiques
        $partisData = [
            ['Union Progressiste pour le Renouveau', 'UP-R', '#ffff00'],
            ['Bloc Républicain', 'BR', '#00ff00'],
            ['Les Démocrates', 'LD', '#ff0000'],
            ['Force Cauris pour un Bénin Emergent', 'FCBE', '#0000ff'],
        ];

        $partis = [];
        foreach ($partisData as $data) {
            $parti = new Parti();
            $parti->setNom($data[0]);
            $parti->setSigle($data[1]);
            $manager->persist($parti);
            $partis[] = $parti;
        }

        // 2. Circonscriptions (Exemple: 15ème et 16ème - Littoral/Cotonou)
        $circosData = [
            ['15ème Circonscription', 'C15'],
            ['16ème Circonscription', 'C16'],
        ];

        foreach ($circosData as $data) {
            $circo = new Circonscription();
            $circo->setNom($data[0]);
            $circo->setCode($data[1]);
            $manager->persist($circo);

            // 3. Centres de Vote (Exemple: EPP Gbégamey)
            $centre = new CentreDeVote();
            $centre->setNom('EPP Gbégamey Zone ' . $data[1]);
            $centre->setCode('CV-' . $data[1] . '-001');
            $centre->setCirconscription($circo);
            // Coordonnées approximatives Cotonou
            $centre->setLatitude(6.3667);
            $centre->setLongitude(2.4167);
            $manager->persist($centre);

            // 4. Bureaux de Vote
            for ($i = 1; $i <= 3; $i++) {
                $bureau = new BureauDeVote();
                $bureau->setNom('Bureau ' . $i);
                $bureau->setCode('BV-' . $data[1] . '-001-' . $i);
                $bureau->setNombreInscrits(450 + ($i * 10));
                $bureau->setCentre($centre);
                $manager->persist($bureau);

                // 5. Création d'un assesseur par bureau
                $assesseur = new User();
                $assesseur->setEmail('assesseur' . $data[1] . $i . '@surevote.bj');
                $assesseur->setNom('Assesseur');
                $assesseur->setPrenom($data[1] . ' ' . $i);
                $assesseur->setRoles(['ROLE_ASSESSEUR']);
                $assesseur->setPassword($this->passwordHasher->hashPassword($assesseur, 'password'));
                $assesseur->setAssignedBureau($bureau);
                $manager->persist($assesseur);
            }
        }

        // 6. Admin
        $admin = new User();
        $admin->setEmail('admin@surevote.bj');
        $admin->setNom('Administrateur');
        $admin->setPrenom('Principal');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        $manager->flush();
    }
}
