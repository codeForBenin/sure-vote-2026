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
        $election->setDateElection(new \DateTimeImmutable('2026-01-08'));
        $election->setIsActive(true);
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

        // 4. Génération des Résultats Fictifs (Pour démo)
        // On crée un Centre et un Bureau fictif par Circo pour y attacher les résultats agrégés
        $faker = Factory::create('fr_FR');

        // Récupérer les entités persistées
        $partisEntities = $manager->getRepository(Parti::class)->findAll();
        $circosEntities = $manager->getRepository(Circonscription::class)->findAll();

        foreach ($circosEntities as $circo) {
            // Créer un centre fictif
            $centre = new CentreDeVote();
            $centre->setNom('Centre Principal ' . $circo->getNom());
            $centre->setCode('CTR-' . $circo->getCode());
            $centre->setCirconscription($circo);
            $centre->setAdresse('Mairie de ' . ($circo->getVilles()[0] ?? 'Ville Principale'));
            $manager->persist($centre);

            // Créer un bureau fictif
            $bureau = new BureauDeVote();
            $bureau->setNom('Bureau Centralisateur');
            $bureau->setCode('BV-' . $circo->getCode());
            $bureau->setCentre($centre);
            $bureau->setNombreInscrits(150000); // Hypothèse
            $manager->persist($bureau);

            // Générer les résultats
            $totalVotesTarget = rand(80000, 120000);

            foreach ($partisEntities as $parti) {
                // Poids aléatoires pour simuler la réalité
                $sigle = $parti->getSigle();
                $basePercent = 0;

                switch ($sigle) {
                    case 'UP-R':
                        $basePercent = 0.35;
                        break;
                    case 'BR':
                        $basePercent = 0.25;
                        break;
                    case 'LD':
                        $basePercent = 0.25;
                        break;
                    case 'FCBE':
                        $basePercent = 0.10;
                        break;
                    default:
                        $basePercent = 0.025;
                        break; // Blancs/Nuls
                }

                // Variation de +/- 10%
                $variation = (rand(-100, 100) / 1000);
                $finalPercent = max(0, $basePercent + $variation);
                $votes = (int) ($totalVotesTarget * $finalPercent);

                $resultat = new \App\Entity\Resultat();
                $resultat->setBureauDeVote($bureau);
                $resultat->setParti($parti);
                $resultat->setNombreVoix($votes);
                $resultat->setIsValidated(true);
                $resultat->setAssesseur($assesseur);

                $manager->persist($resultat);
            }
        }
        
        $manager->flush();
    }
}
