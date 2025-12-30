<?php

namespace App\Controller\Api;

use App\Repository\CirconscriptionRepository;
use App\Repository\ResultatRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\PartiRepository;
use App\Service\SiegeCalculatorService;

#[Route('/api/public', name: 'api_public_')]
class ApiPublicController extends AbstractController
{
    public function __construct(
        private CirconscriptionRepository $circonscriptionRepository,
        private ResultatRepository $resultatRepository,
        private PartiRepository $partiRepository,
        private SiegeCalculatorService $siegeCalculator
    ) {
    }

    #[Route('/circonscriptions', name: 'circonscriptions', methods: ['GET'])]
    public function circonscriptions(): JsonResponse
    {
        $circonscriptions = $this->circonscriptionRepository->findAll();

        $data = [];
        foreach ($circonscriptions as $circo) {
            $data[] = [
                'id' => $circo->getId()->toRfc4122(),
                'nom' => $circo->getNom(),
                'code' => $circo->getCode(),
                'departement' => $circo->getDepartement(),
                'sieges' => $circo->getSieges(),
                'population' => $circo->getPopulation(),
                'villes' => $circo->getVilles(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/partis', name: 'partis_list', methods: ['GET'])]
    public function partis(): JsonResponse
    {
        $partis = $this->partiRepository->findAll();
        $data = [];
        $exclus = ['NULS', 'BLANCS'];

        foreach ($partis as $parti) {
            if (in_array($parti->getSigle(), $exclus))
                continue;

            $data[] = [
                'id' => (string) $parti->getId(),
                'nom' => $parti->getNom(),
                'sigle' => $parti->getSigle(),
                'couleur' => $parti->getCouleur(),
            ];
        }
        return $this->json($data);
    }

    #[Route('/parti/{id}/performance', name: 'parti_performance', methods: ['GET'])]
    public function partiPerformance(string $id): JsonResponse
    {
        $targetParti = $this->partiRepository->findOneBy(['id' => $id]);
        if (!$targetParti) {
            return $this->json(['error' => 'Parti non trouvé'], 404);
        }

        // 1. Pre-load all parties to efficiently map IDs to Entities
        $allParties = $this->partiRepository->findAll();
        $partiMap = [];
        foreach ($allParties as $p) {
            $partiMap[(string) $p->getId()] = $p;
        }

        // 2. Variables for global stats
        $circos = $this->circonscriptionRepository->findAll();

        // For Rank calculation
        $globalVotesPerParti = []; // [partiId => totalVoix]

        // For Seats calculation (theoretical without threshold)
        $globalSeatsPerParti = []; // [partiId => totalSieges]

        // Specific Party Stats
        $report = [];
        $totalValidVotesNational = 0; // Total VALID votes (excluding blancos/nuls)
        $totalPartiVotesNational = 0;
        $circosPassedThreshold = 0;
        $totalCircos = count($circos);

        $exclus = ['NULS', 'BLANCS'];

        foreach ($circos as $circo) {
            $rawResults = $this->resultatRepository->findResultatsParCirconscription($circo->getId());

            // Prepare for calculator and single-pass stats
            $circoDataForCalculator = [];
            $circoValidVotes = 0;
            $currentCircoPartiVotes = 0;

            foreach ($rawResults as $row) {
                $pId = (string) $row['parti_id'];
                $pSigle = $row['parti_sigle'];
                $votes = (int) $row['total_voix'];

                if (!in_array($pSigle, $exclus)) {
                    // Accumulate global votes for Rank
                    if (!isset($globalVotesPerParti[$pId]))
                        $globalVotesPerParti[$pId] = 0;
                    $globalVotesPerParti[$pId] += $votes;

                    // Accumulate for current circo stats
                    $circoValidVotes += $votes;

                    // Data for Seat Calculator
                    if (isset($partiMap[$pId])) {
                        $circoDataForCalculator[] = [
                            'parti' => $partiMap[$pId],
                            'voix' => $votes
                        ];
                    }
                }

                // Specific party tracking
                if ($pId === $id) {
                    $currentCircoPartiVotes = $votes;
                }
            }

            // --- Seat Calculation for this Circo (Hypothetical without national threshold) ---
            // The calculator just applies quotient + strong average on the given set.
            $calcResult = $this->siegeCalculator->calculerSieges($circoDataForCalculator, $circo->getSieges());
            $repartition = $calcResult['repartition'];

            foreach ($repartition as $pId => $infos) {
                if (!isset($globalSeatsPerParti[$pId]))
                    $globalSeatsPerParti[$pId] = 0;
                $globalSeatsPerParti[$pId] += $infos['total_sieges'];
            }

            // --- Specific Party Report Data ---
            $percentage = $circoValidVotes > 0 ? ($currentCircoPartiVotes / $circoValidVotes) * 100 : 0;
            $passed = $percentage >= 20.0;

            if ($passed) {
                $circosPassedThreshold++;
            }

            $totalValidVotesNational += $circoValidVotes;
            $totalPartiVotesNational += $currentCircoPartiVotes;

            $report[] = [
                'circonscription' => $circo->getNom(),
                'code' => $circo->getCode(),
                'votes_parti' => $currentCircoPartiVotes,
                'votes_valides_total' => $circoValidVotes,
                'pourcentage' => round($percentage, 2),
                'seuil_atteint' => $passed
            ];
        }

        // 3. Post-Processing: Rank
        arsort($globalVotesPerParti); // Sort descending by votes
        $rank = 1;
        $myRank = '-';
        foreach ($globalVotesPerParti as $pId => $votes) {
            if ($pId === $id) {
                $myRank = $rank;
                break;
            }
            $rank++;
        }

        // 4. Post-Processing: Seats
        $myTheoreticalSeats = $globalSeatsPerParti[$id] ?? 0;

        // 5. Global Stats
        $nationalPercentage = $totalValidVotesNational > 0 ? ($totalPartiVotesNational / $totalValidVotesNational) * 100 : 0;

        $isEligible = ($circosPassedThreshold === $totalCircos);

        return $this->json([
            'parti' => [
                'nom' => $targetParti->getNom(),
                'sigle' => $targetParti->getSigle(),
                'couleur' => $targetParti->getCouleur()
            ],
            'global' => [
                'votes' => $totalPartiVotesNational,
                'pourcentage_national' => round($nationalPercentage, 2),
                'rank' => $myRank,
                'circos_validees' => $circosPassedThreshold,
                'total_circos' => $totalCircos,
                'is_eligible_everywhere' => $isEligible,
                'total_sieges_theoretical' => $myTheoreticalSeats
            ],
            'details' => $report
        ]);
    }

    #[Route('/resultats/{code}', name: 'resultats_circo', methods: ['GET'])]
    public function resultatsByCirco(string $code): JsonResponse
    {
        $circo = $this->circonscriptionRepository->findOneBy(['code' => $code]);

        if (!$circo) {
            return $this->json(['error' => 'Circonscription non trouvée'], 404);
        }

        // 1. Récupérer tous les partis pour initialiser à 0
        $tousLesPartis = $this->partiRepository->findAll();
        $partisMap = [];
        foreach ($tousLesPartis as $parti) {
            $partisMap[((string) $parti->getId())] = [
                'parti' => $parti,
                'voix' => 0
            ];
        }

        // 2. Récupérer les résultats existants et mettre à jour
        $rawResults = $this->resultatRepository->findResultatsParCirconscription($circo->getId());

        foreach ($rawResults as $row) {
            if (isset($partisMap[(string) $row['parti_id']])) {
                $partisMap[(string) $row['parti_id']]['voix'] = (int) $row['total_voix'];
            }
        }

        // 3. Séparer les votes valides des Nuls/Blancs & Préparer pour le calculateur
        $dataForCalculator = [];
        $totalBlancsNuls = 0;
        $siglesExclus = ['NULS', 'BLANCS'];

        foreach ($partisMap as $pData) {
            $parti = $pData['parti'];

            if (in_array($parti->getSigle(), $siglesExclus)) {
                $totalBlancsNuls += $pData['voix'];
            } else {
                $dataForCalculator[] = [
                    'parti' => $parti,
                    'voix' => $pData['voix']
                ];
            }
        }

        // 4. Calcul des sièges sur les suffrages exprimés UNIQUEMENT (hors blancs/nuls)
        $calculData = $this->siegeCalculator->calculerSieges($dataForCalculator, $circo->getSieges());
        $projectionSieges = $calculData['repartition'];
        $quotientElectoral = $calculData['quotient_electoral'];

        // 5. Formatage de la réponse
        $results = [];
        foreach ($dataForCalculator as $row) {
            $parti = $row['parti'];
            $partiId = (string) $parti->getId();

            $siegesInfo = $projectionSieges[$partiId] ?? ['total_sieges' => 0, 'sieges_femme' => 0, 'sieges_ordinaire' => 0];

            $results[] = [
                'parti' => [
                    'id' => $partiId,
                    'nom' => $parti->getNom(),
                    'sigle' => $parti->getSigle(),
                    'couleur' => $parti->getCouleur(),
                    'affiliation' => $parti->getAffiliation()
                ],
                'voix' => $row['voix'],
                'sieges' => [
                    'total' => $siegesInfo['total_sieges'],
                    'femme' => $siegesInfo['sieges_femme'],
                    'ordinaire' => $siegesInfo['sieges_ordinaire']
                ]
            ];
        }

        return $this->json([
            'circonscription' => [
                'nom' => $circo->getNom(),
                'code' => $circo->getCode(),
                'sieges' => $circo->getSieges(),
                'villes' => $circo->getVilles(),
                'quotient_electoral' => $quotientElectoral
            ],
            'statistiques' => [
                'blancs_nuls' => $totalBlancsNuls
            ],
            'resultats' => $results
        ]);
    }
}
