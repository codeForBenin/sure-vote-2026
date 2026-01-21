<?php

namespace App\Controller\Api;

use App\Repository\CirconscriptionRepository;
use App\Repository\ResultatRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\PartiRepository;
use App\Service\SiegeCalculatorService;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

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
    public function partiPerformance(string $id, UploaderHelper $uploaderHelper): JsonResponse
    {
        $targetParti = $this->partiRepository->findOneBy(['id' => $id]);
        if (!$targetParti) {
            return $this->json(['error' => 'Parti non trouvé'], 404);
        }


        // 1. Préchargement de tous les partis pour mapper efficacement les ID aux Entités
        $allParties = $this->partiRepository->findAll();
        $partiMap = [];
        foreach ($allParties as $p) {
            $partiMap[(string) $p->getId()] = $p;
        }


        // 2. Variables pour les statistiques globales
        $circos = $this->circonscriptionRepository->findAll();


        // Pour le calcul du classement
        $globalVotesPerParti = [];

        // Pour le calcul des sièges (théorique sans seuil)
        $globalSeatsPerParti = [];

        // Statistiques spécifiques au parti
        $report = [];
        $totalValidVotesNational = 0; // Total des votes VALIDES (hors blancs/nuls)
        $totalPartiVotesNational = 0;
        $circosPassedThreshold = 0;
        $totalCircos = count($circos);

        $exclus = ['NULS', 'BLANCS'];

        foreach ($circos as $circo) {
            $rawResults = $this->resultatRepository->findResultatsParCirconscription($circo->getId());


            // Préparation pour le calculateur et les statistiques en une seule passe
            $circoDataForCalculator = [];
            $circoValidVotes = 0;
            $currentCircoPartiVotes = 0;

            foreach ($rawResults as $row) {
                $pId = (string) $row['parti_id'];
                $pSigle = $row['parti_sigle'];
                $votes = (int) $row['total_voix'];

                if (!in_array($pSigle, $exclus)) {
                    // Accumuler les votes globaux pour le Classement
                    if (!isset($globalVotesPerParti[$pId]))
                        $globalVotesPerParti[$pId] = 0;
                    $globalVotesPerParti[$pId] += $votes;

                    // Accumuler pour les statistiques de la circo actuelle
                    $circoValidVotes += $votes;

                    // Données pour le Calculateur de Sièges
                    if (isset($partiMap[$pId])) {
                        $circoDataForCalculator[] = [
                            'parti' => $partiMap[$pId],
                            'voix' => $votes
                        ];
                    }
                }

                // Suivi spécifique du parti
                if ($pId === $id) {
                    $currentCircoPartiVotes = $votes;
                }
            }


            // --- Calcul des Sièges pour cette Circo (Hypothétique sans seuil national) ---
            // Le calculateur applique juste le quotient + la plus forte moyenne sur l'ensemble donné.
            $calcResult = $this->siegeCalculator->calculerSieges($circoDataForCalculator, $circo->getSieges());
            $repartition = $calcResult['repartition'];

            foreach ($repartition as $pId => $infos) {
                if (!isset($globalSeatsPerParti[$pId]))
                    $globalSeatsPerParti[$pId] = 0;
                $globalSeatsPerParti[$pId] += $infos['total_sieges'];
            }

            // --- Données du Rapport Spécifique au Parti ---
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

        // 3. Post-Traitement : Classement
        arsort($globalVotesPerParti); // Tri décroissant par votes
        $rank = 1;
        $myRank = '-';
        foreach ($globalVotesPerParti as $pId => $votes) {
            if ($pId === $id) {
                $myRank = $rank;
                break;
            }
            $rank++;
        }

        // 4. Post-Traitement : Sièges
        $myTheoreticalSeats = $globalSeatsPerParti[$id] ?? 0;

        // 5. Statistiques Globales
        $nationalPercentage = $totalValidVotesNational > 0 ? ($totalPartiVotesNational / $totalValidVotesNational) * 100 : 0;

        $isEligible = ($circosPassedThreshold === $totalCircos);

        $logoUrl = $uploaderHelper->asset($targetParti, 'logoFile');

        return $this->json([
            'parti' => [
                'nom' => $targetParti->getNom(),
                'sigle' => $targetParti->getSigle(),
                'couleur' => $targetParti->getCouleur(),
                'logo' => $logoUrl,
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
    public function resultatsByCirco(string $code, UploaderHelper $uploaderHelper): JsonResponse
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

        $results = [];
        foreach ($dataForCalculator as $row) {
            $parti = $row['parti'];
            $partiId = (string) $parti->getId();

            $siegesInfo = $projectionSieges[$partiId] ?? ['total_sieges' => 0, 'sieges_femme' => 0, 'sieges_ordinaire' => 0];

            $logoUrl = $uploaderHelper->asset($parti, 'logoFile');
            $results[] = [
                'parti' => [
                    'id' => $partiId,
                    'nom' => $parti->getNom(),
                    'sigle' => $parti->getSigle(),
                    'couleur' => $parti->getCouleur(),
                    'affiliation' => $parti->getAffiliation(),
                    'logo' => $logoUrl,
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
    #[Route('/simulate-seats', name: 'simulate_seats', methods: ['POST'])]
    public function simulateSeats(
        Request $request,
        UploaderHelper $uploaderHelper
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $sieges = (int) ($data['sieges'] ?? 0);
        $votes = $data['votes'] ?? [];

        if ($sieges <= 0) {
            return $this->json(['error' => 'Nombre de sièges invalide'], 400);
        }

        $inputForCalculator = [];
        $exclus = ['NULS', 'BLANCS'];

        // Optimisation : récupérer tous les partis une fois si nécessaire, mais find() est généralement mis en cache par doctrine.
        // Ou findBy(['id' => array_keys($votes)]).
        // Pour faire simple, bouclons.
        foreach ($votes as $partiId => $voix) {
            $parti = $this->partiRepository->find($partiId);
            if ($parti && !in_array($parti->getSigle(), $exclus)) {
                $inputForCalculator[] = [
                    'parti' => $parti,
                    'voix' => (int) $voix
                ];
            }
        }

        $result = $this->siegeCalculator->calculerSieges($inputForCalculator, $sieges);
        $repartition = $result['repartition'];
        $quotient = $result['quotient_electoral'];

        // Calculer le total des votes pour le pourcentage
        $totalVoix = 0;
        foreach ($repartition as $info) {
            $totalVoix += $info['voix'];
        }

        // Formater la sortie
        $formatted = [];
        foreach ($repartition as $pId => $info) {
            $parti = $info['parti'];
            $logoUrl = $uploaderHelper->asset($parti, 'logoFile');

            $pourcentage = $totalVoix > 0 ? ($info['voix'] / $totalVoix) * 100 : 0;

            $formatted[] = [
                'parti' => [
                    'nom' => $parti->getNom(),
                    'sigle' => $parti->getSigle(),
                    'couleur' => $parti->getCouleur(),
                    'logo' => $logoUrl
                ],
                'voix' => $info['voix'],
                'pourcentage' => round($pourcentage, 2),
                'total_sieges' => $info['total_sieges'],
                'sieges_femme' => $info['sieges_femme'],
                'sieges_ordinaire' => $info['sieges_ordinaire']
            ];
        }

        // Trier par sièges décroissant puis votes décroissant
        usort($formatted, function ($a, $b) {
            if ($b['total_sieges'] === $a['total_sieges']) {
                return $b['voix'] <=> $a['voix'];
            }
            return $b['total_sieges'] <=> $a['total_sieges'];
        });

        return $this->json([
            'quotient_electoral' => $quotient,
            'repartition' => $formatted
        ]);
    }
}
