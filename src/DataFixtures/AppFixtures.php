<?php

namespace App\DataFixtures;

use App\Entity\BureauDeVote;
use App\Entity\CentreDeVote;
use App\Entity\Circonscription;
use App\Entity\Election;
use App\Entity\Parti;
use App\Entity\Participation;
use App\Entity\Resultat;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
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
        $election->setDateElection(new \DateTimeImmutable('2026-01-11'));
        $election->setIsActive(true);
        // $election->setNombreInscrits(rand(4000000, 6500000)); // On laisse null pour le calcul dynamique
        $election->setNombreBureauxDeVote(rand(1000, 3000));
        $election->setSiegesPourvoir(109);
        $manager->persist($election);

        // 1. Partis Politiques (Couleurs officiellles approx)
        $partisData = [
            ['Union Progressiste pour le Renouveau', 'UP-R', '#FCD116', 'Mouvance'], // Jaune
            ['Bloc Républicain', 'BR', '#009739', 'Mouvance'], // Vert
            ['Les Démocrates', 'LD', '#EF4135', 'Opposition'], // Rouge feu (pas orange)
            ['Force Cauris pour un Bénin Emergent', 'FCBE', '#0000ff', 'Opposition'],
            ['Bulletins Nuls', 'NULS', '#94a3b8', 'Autre'], // Gris Slate 400
            ['Votes Blancs', 'BLANCS', '#cbd5e1', 'Autre'], // Gris Slate 300
        ];

        foreach ($partisData as $data) {
            $parti = new Parti();
            $parti->setNom($data[0]);
            $parti->setSigle($data[1]);
            $parti->setCouleur($data[2]);
            $parti->setAffiliation($data[3] ?? 'Autre');
            $manager->persist($parti);
        }

        // 2. Circonscriptions (Source: Code Électoral / Images fournies)
        // Format: [Nom, Code, Villes (array), Sièges Ordinaires + 1 (Femme) = Total, Departement (Approximatif)]
        $circosData = [
            ['Première circonscription électorale', 'C01', ['Kandi', 'Malanville', 'Karimama'], 3 + 1, 'Alibori'],
            ['Deuxième circonscription électorale', 'C02', ['Gogounou', 'Banikoara', 'Ségbana'], 3 + 1, 'Alibori'],
            ['Troisième circonscription électorale', 'C03', ['Boukoumbé', 'Cobly', 'Matéri', 'Tanguiéta'], 3 + 1, 'Atacora'],
            ['Quatrième circonscription électorale', 'C04', ['Kérou', 'Kouandé', 'Natitingou', 'Péhunco', 'Toucountouna'], 4 + 1, 'Atacora'],
            ['Cinquième circonscription électorale', 'C05', ['Allada', 'Kpomassè', 'Ouidah', 'Toffo', 'Tori-Bossito'], 5 + 1, 'Atlantique'],
            ['Sixième circonscription électorale', 'C06', ['Abomey-Calavi', 'Sô-Ava', 'Zè'], 7 + 1, 'Atlantique'],
            ['Septième circonscription électorale', 'C07', ['Nikki', 'Bembèrèkè', 'Sinendé', 'Kalalé'], 4 + 1, 'Borgou'],
            ['Huitième circonscription électorale', 'C08', ['Pèrèrè', 'Parakou', 'Tchaourou', "N'Dali"], 5 + 1, 'Borgou'],
            ['Neuvième circonscription électorale', 'C09', ['Bantè', 'Dassa-Zoumè', 'Savalou'], 3 + 1, 'Collines'],
            ['Dixième circonscription électorale', 'C10', ['Ouèssè', 'Glazoué', 'Savè'], 3 + 1, 'Collines'],
            ['Onzième circonscription électorale', 'C11', ['Aplahoué', 'Djakotomè', 'Klouékanmè'], 3 + 1, 'Couffo'],
            ['Douzième circonscription électorale', 'C12', ['Dogbo', 'Lalo', 'Toviklin'], 3 + 1, 'Couffo'],
            ['Treizième circonscription électorale', 'C13', ['Djougou'], 2 + 1, 'Donga'],
            ['Quatorzième circonscription électorale', 'C14', ['Bassila', 'Copargo', 'Ouaké'], 2 + 1, 'Donga'],
            ['Quinzième circonscription électorale', 'C15', ['Cotonou (1er au 6ème arrondissement)'], 3 + 1, 'Littoral'],
            ['Seizième circonscription électorale', 'C16', ['Cotonou (7ème au 13ème arrondissement)'], 4 + 1, 'Littoral'],
            ['Dix-septième circonscription électorale', 'C17', ['Athiémé', 'Comè', 'Grand-Popo'], 2 + 1, 'Mono'],
            ['Dix-huitième circonscription électorale', 'C18', ['Bopa', 'Lokossa', 'Houéyogbé'], 3 + 1, 'Mono'],
            ['Dix-neuvième circonscription électorale', 'C19', ['Adjarra', 'Aguégués', 'Porto-Novo', 'Sèmè-Kpodji'], 5 + 1, 'Ouémé'],
            ['Vingtième circonscription électorale', 'C20', ['Adjohoun', 'Akpro-Missérété', 'Avrankou', 'Bonou', 'Dangbo'], 5 + 1, 'Ouémé'],
            ['Vingt-et-une circonscription électorale', 'C21', ['Adja-Ouèrè', 'Ifangni', 'Sakété'], 3 + 1, 'Plateau'],
            ['Vingt-deuxième circonscription électorale', 'C22', ['Kétou', 'Pobè'], 2 + 1, 'Plateau'],
            ['Vingt-troisième circonscription électorale', 'C23', ['Abomey', 'Agbangnizoun', 'Bohicon', 'Djidja'], 4 + 1, 'Zou'],
            ['Vingt-quatrième circonscription électorale', 'C24', ['Covè', 'Ouinhi', 'Zagnanado', 'Za-Kpota', 'Zogbodomey'], 4 + 1, 'Zou'],
        ];

        foreach ($circosData as $data) {
            $circo = new Circonscription();
            $circo->setNom($data[0]);
            $circo->setCode($data[1]);
            $circo->setVilles($data[2]);
            $circo->setSieges($data[3]);
            $circo->setDepartement($data[4]); // On utilise l'info de département
            $circo->setPopulation(0); // Pas d'info population précise
            $manager->persist($circo);
        }

        // 3. Admin & Assesseur
        $admin = new User();
        $admin->setEmail('admin@surevote.bj');
        $admin->setNom('Administrateur');
        $admin->setPrenom('Principal');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        $assesseur = new User();
        $assesseur->setEmail('assesseur@surevote.bj');
        $assesseur->setNom('Assesseur');
        $assesseur->setPrenom('Lambda');
        $assesseur->setRoles(['ROLE_ASSESSEUR']);
        $assesseur->setPassword($this->passwordHasher->hashPassword($assesseur, 'password'));
        $manager->persist($assesseur);

        $manager->flush(); // Save reference data first

        // 4. Génération des Centres et Bureaux (Pour démo et stats)
        $centreNames = [
            'EPP Kpankpan',
            'CEG Zogbo',
            'Maison des Jeunes Agla',
            'EPP Cadjehoun',
            'CEG Gbégamey',
            'Complexe Scolaire Ste Rita',
            'EPP Fifadji',
            'CEG Godomey',
            'EPP Vèdoko',
            'Maison du Peuple',
            'CEG Sainte Rita',
            'EPP Avaria',
            'CEG Akpakpa Centre',
            'EPP Sodjèatina',
            'CEG Nokoué',
            'EPP Dantokpa',
            'CEG Le Méridien',
            'EPP Ganhi',
            'CEG Entente',
            'EPP Sikècodji'
        ];

        $bureauSuffixes = ['Bureau A', 'Bureau B', 'Bureau C', 'Bureau D', 'Bureau E', 'Bureau F'];

        // On récupère les entités persistées
        $partisEntities = $manager->getRepository(Parti::class)->findAll();
        $circosEntities = $manager->getRepository(Circonscription::class)->findAll();

        // On crée quelques assesseurs de test
        $assesseurs = [];
        for ($i = 0; $i < 5; $i++) {
            $user = new User();
            $user->setEmail((string) 'assesseur' . $i . '@surevote.bj');
            $user->setNom('Assesseur');
            $user->setPrenom('Test ' . ($i + 1));
            $user->setRoles(['ROLE_ASSESSEUR']);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
            $manager->persist($user);
            $assesseurs[] = $user;
        }

        // On assigne le premier bureau de vote au compte de démo "assesseur@surevote.bj"
        $demoAssesseur = $assesseur;
        $assesseurs[] = $demoAssesseur;

        // Création des Bureaux
        $bureauCounter = 0;

        foreach ($circosEntities as $index => $circo) {
            // On ne s'attarde pas sur toutes les circonscriptions, on en peuple certaines
            // Ou on met un bureau par circo + d'autres au hasard

            // Créer 2 centres par circonscription
            for ($c = 0; $c < 2; $c++) {
                $centre = new CentreDeVote();
                $centreName = $centreNames[array_rand($centreNames)] . ' ' . $circo->getCode();
                $centre->setNom($centreName);
                $centre->setCode('CTR-' . $circo->getCode() . '-' . $c);
                $centre->setCirconscription($circo);
                $centre->setAdresse('Quartier ' . $c . ' ' . ($circo->getVilles()[0] ?? 'Ville'));
                $manager->persist($centre);

                // Créer 2 à 3 bureaux par centre
                $nbBureaux = rand(2, 3);
                for ($b = 0; $b < $nbBureaux; $b++) {
                    $bureau = new BureauDeVote();
                    $bureau->setNom($bureauSuffixes[$b]);
                    $bureau->setCode('BV-' . $circo->getCode() . '-' . $c . $b);
                    $bureau->setCentre($centre);
                    $inscrits = rand(300, 500);
                    $bureau->setNombreInscrits($inscrits);

                    $manager->persist($bureau);
                    $bureauCounter++;

                    // Assigner un assesseur (surtout pour le compte de démo)
                    if ($bureauCounter === 1) {
                        $assesseur->setAssignedBureau($bureau);
                    } else if (rand(0, 10) > 8) { // Quelques bureaux assignés aléatoirement
                        $randomAssesseur = $assesseurs[array_rand($assesseurs)];
                        // Si l'assesseur n'a pas déjà un bureau
                        if (!$randomAssesseur->getAssignedBureau()) {
                            $randomAssesseur->setAssignedBureau($bureau);
                        }
                    }

                    // --- Génération des Participations (Chart) ---
                    // On simule une journée de 8h à 18h
                    $hours = [8, 10, 12, 14, 16];
                    $currentVotants = 0;

                    foreach ($hours as $hour) {
                        // Progression aléatoire (plus soutenue pour atteindre ~70%)
                        $increment = rand(45, 85);
                        $currentVotants += $increment;

                        if ($currentVotants > $inscrits) {
                            $currentVotants = $inscrits;
                        }

                        $participation = new Participation();
                        $participation->setBureauDeVote($bureau);
                        $participation->setAssesseur($demoAssesseur); // Peu importe l'auteur pour la stat
                        $participation->setNombreVotants($currentVotants);

                        // Heure du pointage (Basé sur la date de l'élection)
                        $datePointage = \DateTimeImmutable::createFromInterface($election->getDateElection());
                        $datePointage = $datePointage->setTimezone(new \DateTimeZone('Africa/Porto-Novo'));
                        $datePointage = $datePointage->setTime($hour, rand(0, 59));

                        $participation->setHeurePointage($datePointage);
                        $manager->persist($participation);
                    }

                    // --- Génération des Résultats ---
                    // Uniquement pour certains bureaux (simuler bureaux dépouillés)
                    if (rand(0, 1)) {
                        $votesRestants = $currentVotants;

                        foreach ($partisEntities as $pIndex => $parti) {
                            if ($pIndex === count($partisEntities) - 1) {
                                $votes = $votesRestants;
                            } else {
                                $votes = (int) ($votesRestants * (rand(10, 40) / 100));
                            }
                            $votesRestants -= $votes;
                            if ($votesRestants < 0)
                                $votesRestants = 0;

                            $resultat = new Resultat();
                            $resultat->setBureauDeVote($bureau);
                            $resultat->setParti($parti);
                            $resultat->setNombreVoix($votes);
                            $resultat->setIsValidated(true);
                            $resultat->setAssesseur($demoAssesseur);
                            $manager->persist($resultat);
                        }
                    }
                }
            }
        }
        
        $manager->flush();
    }
}
