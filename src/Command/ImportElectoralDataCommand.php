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
        // Simple transliteration: remove accents, uppercase
        $str = \transliterator_transliterate('Any-Latin; Latin-ASCII; Upper()', $input);
        // Keep only letters and numbers
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

        // 1. Preload Circonscriptions
        $circonscriptions = $this->em->getRepository(Circonscription::class)->findAll();
        $circoMap = []; // 'NORMALIZED_COMMUNE' => Circonscription

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

        // ... (rest of the file opening logic remains similar)

        // 2. Read File
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

            // 1. Find Circonscription Key
            $circoKey = $this->normalizeKey($commune);

            // SPECIAL HANDLING FOR COTONOU
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

            // 2. Resolve CentreDeVote
            // Smart Slug: Remove duplicate commune/village names from centre name
            // Ex: Centre "EPP BANIKOARA" in Commune "BANIKOARA" -> Code "BANIKOARA-EPP" instead of "BANIKOARA-EPP-BANIKOARA"

            $communeSlug = strtoupper($this->slugger->slug($commune)->toString());
            $centreNameSlug = strtoupper($this->slugger->slug($nomCentre)->toString());

            // Remove Commune Prefix if exists (e.g. BANIKOARA-EPP -> EPP)
            if (str_starts_with($centreNameSlug, $communeSlug . '-')) {
                $cleanCentreSlug = substr($centreNameSlug, strlen($communeSlug) + 1);
            } elseif ($centreNameSlug === $communeSlug) {
                $cleanCentreSlug = 'CENTRE-' . $centreNameSlug; // Fallback if name is exactly identical
            } else {
                $cleanCentreSlug = $centreNameSlug;
            }

            // Also check for Village Prefix if different from Commune
            $villageSlug = strtoupper($this->slugger->slug($village)->toString());
            if ($villageSlug !== $communeSlug && str_starts_with($cleanCentreSlug, $villageSlug . '-')) {
                $cleanCentreSlug = substr($cleanCentreSlug, strlen($villageSlug) + 1);
            }

            $centreCode = $communeSlug . '-' . $cleanCentreSlug;

            // Limit code length to 50
            if (strlen($centreCode) > 50) {
                $centreCode = substr($centreCode, 0, 50);
            }

            if (isset($centresCache[$centreCode])) {
                $centre = $centresCache[$centreCode];
                // Check if centre is managed; if not, get reference using ID to avoid detached entity error
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
                // Check DB
                $centre = $this->em->getRepository(CentreDeVote::class)->findOneBy(['code' => $centreCode]);

                if (!$centre) {
                    $centre = new CentreDeVote();
                    $centre->setCode($centreCode);
                    $centre->setNom($nomCentre);
                    $centre->setCommune($commune);
                    $centre->setArrondissement($arrondissement);
                    $centre->setVillageQuartier($village);
                    $centre->setDepartement($departement);

                    // Set Lat/Lon only on creation or maybe update? Let's update if present
                    if ($latitude)
                        $centre->setLatitude($latitude);
                    if ($longitude)
                        $centre->setLongitude($longitude);

                    // Handle Circonscription Reference safely
                    $cachedCirco = $circoMap[$circoKey];
                    $circoRef = $this->em->getReference(Circonscription::class, $cachedCirco->getId());
                    $centre->setCirconscription($circoRef);

                    $centre->updateSearchContent();

                    $this->em->persist($centre);
                    $createdCentres++;
                } else {
                    // Update search content for existing centres too (in case of re-import)
                    $centre->updateSearchContent();
                    // Update Geolocation if existing and we have new data
                    $updated = false;
                    if ($latitude && $centre->getLatitude() !== $latitude) {
                        $centre->setLatitude($latitude);
                        $updated = true;
                    }
                    if ($longitude && $centre->getLongitude() !== $longitude) {
                        $centre->setLongitude($longitude);
                        $updated = true;
                    }
                    // Ensure address is synced if needed? (Not using address from CSV here yet, user said no address column used)
                }

                $centresCache[$centreCode] = $centre;
            }

            // 3. Resolve BureauDeVote
            // OLD LOGIC: Code based on name -> Risk of duplicates
            // NEW LOGIC: Arrondissement + Village + PV{Index}

            $arrondSlug = strtoupper($this->slugger->slug($arrondissement)->toString());
            $villageSlug = strtoupper($this->slugger->slug($village)->toString());

            // Composite key for the counter
            $counterKey = $arrondSlug . '_' . $villageSlug;

            if (!isset($counters[$counterKey])) {
                $counters[$counterKey] = 0;
            }
            $counters[$counterKey]++;

            $bureauIndex = $counters[$counterKey];

            // Code: ARRONDISSEMENT-VILLAGE-PV{X}
            // We strip 'ARRONDISSEMENT' prefix if present in village to shorten
            $cleanVillageSlug = $villageSlug;
            // Shortening logic if needed, but let's stick to user request: Arrond + Quartier + PV{X}

            $bureauCode = sprintf('%s-%s-PV%d', $arrondSlug, $cleanVillageSlug, $bureauIndex);

            if (strlen($bureauCode) > 100) {
                // Should fits, but safety check
                $bureauCode = substr($bureauCode, 0, 100);
            }

            // First check if we've already seen this bureau in this command execution (Internal Safety)
            if (isset($processedBureaux[$bureauCode])) {
                // This implies we generated the SAME code twice in this run? 
                // With the counter logic, this should NOT happen unless $counterKey collision.
                $skipped++;
                if ($skipped <= 10) {
                    $io->warning(sprintf("Skipped Line %d: Duplicate Generated Code '%s'.", $rowNum, $bureauCode));
                }
                continue;
            }

            // Check if Bureau exists in DB
            $bureau = $this->em->getRepository(BureauDeVote::class)->findOneBy(['code' => $bureauCode]);

            if (!$bureau) {
                $bureau = new BureauDeVote();
                $bureau->setCode($bureauCode);
                $bureau->setNom($posteName);
                $bureau->setNombreInscrits(0); // Default
                $bureau->setCentre($centre);

                $this->em->persist($bureau);
                $createdBureaux++;
            } else {
                // Update existing bureau (e.g. name update or attach to new centre if changed)
                // For now just ensure centre match?
                if ($bureau->getCentre()->getId() !== $centre->getId()) {
                    $bureau->setCentre($centre); // Update parent if moved
                }
            }

            // Mark as processed
            $processedBureaux[$bureauCode] = true;

            if (($i % $batchSize) === 0) {
                $this->em->flush();
                $this->em->clear();

                // Clear the cache of centres because they are now detached
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
