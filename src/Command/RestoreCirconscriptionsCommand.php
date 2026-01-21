<?php

namespace App\Command;

use App\Entity\Circonscription;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:restore-circonscriptions',
    description: 'Restores Circonscriptions (1 per Commune) from CSV to recover from data deletion',
)]
class RestoreCirconscriptionsCommand extends Command
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
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');

        if (!file_exists($filePath)) {
            $io->error("File not found: $filePath");
            return Command::FAILURE;
        }

        $io->title("Restoring Circonscriptions from CSV");

        if (($handle = fopen($filePath, "r")) === FALSE) {
            $io->error("Could not open file.");
            return Command::FAILURE;
        }

        $row = 0;
        $communesFound = []; // 'COMMUNE_SLUG' => ['name' => 'Commune', 'dept' => 'Dept']

        while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
            $row++;
            if ($row === 1) continue; // Skip Header

            $departement = trim($data[1] ?? '');
            $commune = trim($data[2] ?? '');

            if (empty($commune)) continue;

            $slug = strtoupper($this->slugger->slug($commune)->toString());
            
            if (!isset($communesFound[$slug])) {
                $communesFound[$slug] = [
                    'name' => $commune,
                    'dept' => $departement
                ];
            }
        }
        fclose($handle);

        $io->info("Found " . count($communesFound) . " unique communes/circonscriptions to restore.");

        $count = 0;
        foreach ($communesFound as $slug => $info) {
            // Check if exists
            $existing = $this->em->getRepository(Circonscription::class)->findOneBy(['code' => $slug]);

            if (!$existing) {
                $circo = new Circonscription();
                $circo->setCode($slug);
                $circo->setNom("Circonscription de " . $info['name']);
                $circo->setDepartement($info['dept']);
                $circo->setVilles([$info['name']]); // 1 city per circo for restoration
                $circo->setSieges(0);
                $circo->setPopulation(0);

                $this->em->persist($circo);
                $count++;
            }
        }

        $this->em->flush();

        $io->success("Restored $count circonscriptions.");
        $io->note("You can now run app:import-electoral-data to restore Centres and Bureaux.");

        return Command::SUCCESS;
    }
}
