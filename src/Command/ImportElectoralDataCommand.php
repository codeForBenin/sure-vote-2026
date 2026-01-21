<?php

namespace App\Command;

use App\Entity\BureauDeVote;
use App\Entity\CentreDeVote;
use App\Entity\Circonscription;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:import-electoral-data',
    description: 'Imports electoral data (Centres and Bureaux) from a CSV file',
)]
class ImportElectoralDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private SluggerInterface $slugger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the CSV file')
            ->addOption('delimiter', 'd', InputOption::VALUE_OPTIONAL, 'CSV Delimiter', ';')
            ->addOption('no-header', null, InputOption::VALUE_NONE, 'Set if file has no header row')
        ;
    }

    private function normalizeKey(string $input): string
    {
        // Translittération simple : supprimer les accents et mettre en majuscule
        $str = \transliterator_transliterate('Any-Latin; Latin-ASCII; Upper()', $input);
        // Ne garder que les lettres et les chiffres
        return preg_replace('/[^A-Z0-9]/', '', $str);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');
        $delimiter = $input->getOption('delimiter');
        $hasHeader = !$input->getOption('no-header');

        if (!file_exists($filePath)) {
            $io->error("File not found: $filePath");
            return Command::FAILURE;
        }

        $io->info("Loading Circonscriptions map...");

        // 1. Préchargement des Circonscriptions
        $circonscriptions = $this->em->getRepository(Circonscription::class)->findAll();
        $circoMap = []; // 'COMMUNE_NORMALISEE' => Circonscription

        foreach ($circonscriptions as $circo) {
            $villes = $circo->getVilles();
            if (is_array($villes)) {
                foreach ($villes as $ville) {
                    $key = $this->normalizeKey($ville);
                    if ($key) {
                        $circoMap[$key] = $circo;
                    }
                }
            }
        }

        $io->info("Mapped " . count($circoMap) . " communes (normalized) to circonscriptions.");

        // ... (lecture de fichier)

        // 2. Lecture du fichier
        if (($handle = fopen($filePath, "r")) === FALSE) {
            $io->error("Could not open file.");
            return Command::FAILURE;
        }

        $batchSize = 50;
        $i = 0;
        $createdCentres = 0;
        $createdBureaux = 0;
        $skipped = 0;
        $rowNum = 0;

        // ... (counters init)

        $lines = count(file($filePath));
        $progressBar = new ProgressBar($output, $lines);
        $progressBar->start();

        $centresCache = [];
        $processedBureaux = [];
        $counters = [];
        $headerMap = [];

        if ($hasHeader) {
            rewind($handle);
            $headerRow = fgetcsv($handle, 0, $delimiter);
            foreach ($headerRow as $index => $colName) {
                $headerMap[strtoupper(trim($colName))] = $index;
            }
            rewind($handle);
            $rowNum = 0;
        }

        while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            $rowNum++;
            $progressBar->advance();

            if ($rowNum === 1 && $hasHeader) {
                continue;
            }

            if (count($data) < 7) {
                $skipped++;
                // ... log warning ...
                continue;
            }

            $latIndex = $headerMap['LATITUDE'] ?? -1;
            $lonIndex = $headerMap['LONGITUDE'] ?? -1;

            $departement = trim($data[1] ?? '');
            $commune = trim($data[2] ?? '');
            $arrondissement = trim($data[3] ?? '');
            $village = trim($data[4] ?? '');
            $nomCentre = trim($data[5] ?? '');
            $posteName = trim($data[6] ?? '');

            // Latitude / Longitude
            $latitude = null;
            $longitude = null;
            if ($latIndex >= 0 && isset($data[$latIndex])) {
                $val = str_replace(',', '.', $data[$latIndex]);
                if (is_numeric($val))
                    $latitude = (float) $val;
            }
            if ($lonIndex >= 0 && isset($data[$lonIndex])) {
                $val = str_replace(',', '.', $data[$lonIndex]);
                if (is_numeric($val))
                    $longitude = (float) $val;
            }

            if (empty($commune) || empty($nomCentre)) {
                $skipped++;
                // ... log warning ...
                continue;
            }

            // 1. Trouver la clé de la Circonscription
            $circoKey = $this->normalizeKey($commune);

            // GESTION SPÉCIALE POUR COTONOU
            if ($circoKey === 'COTONOU') {
                $arrondNormal = $this->normalizeKey($arrondissement);
                preg_match('/^(\d+)/', $arrondNormal, $matches);
                $arrondNum = isset($matches[1]) ? (int) $matches[1] : 0;

                if ($arrondNum >= 1 && $arrondNum <= 6) {
                    $circoKey = $this->normalizeKey("Cotonou (1er au 6ème arrondissement)");
                } elseif ($arrondNum >= 7 && $arrondNum <= 13) {
                    $circoKey = $this->normalizeKey("Cotonou (7ème au 13ème arrondissement)");
                }
            }

            if (!isset($circoMap[$circoKey])) {
                $skipped++;
                if ($skipped <= 10) {
                    $io->note("Skipped Line (Assumed Missing Circonscription Mapping): Commune='$commune' (Key='$circoKey'). Arrond='$arrondissement'.");
                }
                continue;
            }

            // 2. Résolution du CentreDeVote
            // Slug intelligent : supprimer les doublons de noms commune/village
            // Ex: Centre "EPP BANIKOARA" dans Commune "BANIKOARA" -> Code "BANIKOARA-EPP" au lieu de "BANIKOARA-EPP-BANIKOARA"

            $communeSlug = strtoupper($this->slugger->slug($commune)->toString());
            $centreNameSlug = strtoupper($this->slugger->slug($nomCentre)->toString());

            // Supprimer le préfixe de la commune s'il existe (ex: BANIKOARA-EPP -> EPP)
            if (str_starts_with($centreNameSlug, $communeSlug . '-')) {
                $cleanCentreSlug = substr($centreNameSlug, strlen($communeSlug) + 1);
            } elseif ($centreNameSlug === $communeSlug) {
                $cleanCentreSlug = 'CENTRE-' . $centreNameSlug; // Solution de repli si le nom est exactement identique
            } else {
                $cleanCentreSlug = $centreNameSlug;
            }

            // Vérifier aussi le préfixe du Village s'il diffère de la Commune
            $villageSlug = strtoupper($this->slugger->slug($village)->toString());
            if ($villageSlug !== $communeSlug && str_starts_with($cleanCentreSlug, $villageSlug . '-')) {
                $cleanCentreSlug = substr($cleanCentreSlug, strlen($villageSlug) + 1);
            }

            $centreCode = $communeSlug . '-' . $cleanCentreSlug;

            // Limiter la longueur du code à 50
            if (strlen($centreCode) > 50) {
                $centreCode = substr($centreCode, 0, 50);
            }

            if (isset($centresCache[$centreCode])) {
                $centre = $centresCache[$centreCode];
                // Vérifier si le centre est géré ; sinon, obtenir une référence par ID pour éviter l'erreur d'entité détachée
                if (!$this->em->contains($centre)) {
                    try {
                        $centre = $this->em->getReference(CentreDeVote::class, $centre->getId());
                        $centresCache[$centreCode] = $centre;
                    } catch (\Exception $e) {
                        $centre = $this->em->getRepository(CentreDeVote::class)->findOneBy(['code' => $centreCode]);
                        $centresCache[$centreCode] = $centre;
                    }
                }
            } else {
                // Vérifier en base de données
                $centre = $this->em->getRepository(CentreDeVote::class)->findOneBy(['code' => $centreCode]);

                if (!$centre) {
                    $centre = new CentreDeVote();
                    $centre->setCode($centreCode);
                    $centre->setNom($nomCentre);
                    $centre->setCommune($commune);
                    $centre->setArrondissement($arrondissement);
                    $centre->setVillageQuartier($village);
                    $centre->setDepartement($departement);

                    // Mettre à jour Lat/Lon seulement à la création ou la mise à jour si présent
                    if ($latitude)
                        $centre->setLatitude($latitude);
                    if ($longitude)
                        $centre->setLongitude($longitude);

                    // Gérer la référence de Circonscription en toute sécurité
                    $cachedCirco = $circoMap[$circoKey];
                    $circoRef = $this->em->getReference(Circonscription::class, $cachedCirco->getId());
                    $centre->setCirconscription($circoRef);

                    $centre->updateSearchContent();

                    $this->em->persist($centre);
                    $createdCentres++;
                } else {
                    // Mettre à jour le contenu de recherche pour les centres existants aussi (en cas de ré-import)
                    $centre->updateSearchContent();
                    // Mettre à jour la Géolocalisation si existante et nouvelles données disponibles
                    $updated = false;
                    if ($latitude && $centre->getLatitude() !== $latitude) {
                        $centre->setLatitude($latitude);
                        $updated = true;
                    }
                    if ($longitude && $centre->getLongitude() !== $longitude) {
                        $centre->setLongitude($longitude);
                        $updated = true;
                    }
                    // S'assurer que l'adresse est synchronisée si nécessaire (Non utilisé ici pour l'instant)
                }

                $centresCache[$centreCode] = $centre;
            }

            // 3. Résolution du BureauDeVote
            // ANCIENNE LOGIQUE : Code basé sur le nom -> Risque de doublons
            // NOUVELLE LOGIQUE : Arrondissement + Village + PV{Index}

            $arrondSlug = strtoupper($this->slugger->slug($arrondissement)->toString());
            $villageSlug = strtoupper($this->slugger->slug($village)->toString());

            // Clé composite pour le compteur
            $counterKey = $arrondSlug . '_' . $villageSlug;

            if (!isset($counters[$counterKey])) {
                $counters[$counterKey] = 0;
            }
            $counters[$counterKey]++;

            $bureauIndex = $counters[$counterKey];

            // Code: ARRONDISSEMENT-VILLAGE-PV{X}
            // Nous supprimons le préfixe 'ARRONDISSEMENT' si présent dans le village pour raccourcir
            $cleanVillageSlug = $villageSlug;
            // Logique de raccourcissement si nécessaire, mais restons sur la demande utilisateur : Arrond + Quartier + PV{X}

            $bureauCode = sprintf('%s-%s-PV%d', $arrondSlug, $cleanVillageSlug, $bureauIndex);

            if (strlen($bureauCode) > 100) {
                // Devrait tenir, mais vérification de sécurité
                $bureauCode = substr($bureauCode, 0, 100);
            }

            // Vérifier d'abord si nous avons déjà vu ce bureau dans cette exécution (Sécurité Interne)
            if (isset($processedBureaux[$bureauCode])) {
                // Cela implique que nous avons généré le MÊME code deux fois dans cette exécution ?
                // Avec la logique de compteur, cela ne devrait PAS arriver sauf collision de $counterKey.
                $skipped++;
                if ($skipped <= 10) {
                    $io->warning(sprintf("Skipped Line %d: Duplicate Generated Code '%s'.", $rowNum, $bureauCode));
                }
                continue;
            }

            // Vérifier si le Bureau existe en BDD
            $bureau = $this->em->getRepository(BureauDeVote::class)->findOneBy(['code' => $bureauCode]);

            if (!$bureau) {
                $bureau = new BureauDeVote();
                $bureau->setCode($bureauCode);
                $bureau->setNom($posteName);
                $bureau->setNombreInscrits(0); // Par défaut
                $bureau->setCentre($centre);

                $this->em->persist($bureau);
                $createdBureaux++;
            } else {
                // Mettre à jour le bureau existant (ex: mise à jour nom ou rattachement à un nouveau centre si changé)
                // Pour l'instant juste s'assurer que le centre correspond
                if ($bureau->getCentre()->getId() !== $centre->getId()) {
                    $bureau->setCentre($centre); // Mettre à jour le parent si déplacé
                }
            }

            // Marquer comme traité
            $processedBureaux[$bureauCode] = true;

            if (($i % $batchSize) === 0) {
                $this->em->flush();
                $this->em->clear();

                // Vider le cache des centres car ils sont maintenant détachés
                $centresCache = [];
            }
            $i++;
        }

        $this->em->flush();
        $progressBar->finish();
        $io->newLine();

        $io->success(sprintf("Import completed. Centres created: %d. Bureaux created: %d. Skipped lines: %d", $createdCentres, $createdBureaux, $skipped));

        return Command::SUCCESS;
    }
}
