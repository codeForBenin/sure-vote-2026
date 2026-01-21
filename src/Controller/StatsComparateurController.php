<?php

namespace App\Controller;

use App\Repository\CirconscriptionRepository;
use App\Repository\PartiRepository;
use App\Repository\ResultatRepository;
use App\Service\SiegeCalculatorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class StatsComparateurController extends AbstractController
{
    #[Route('/stats/parlement', name: 'app_stats_parlement')]
    public function index(
        CirconscriptionRepository $circonscriptionRepository,
        ResultatRepository $resultatRepository,
        PartiRepository $partiRepository,
        SiegeCalculatorService $siegeCalculator,
        UploaderHelper $uploaderHelper
    ): Response {
        $circos = $circonscriptionRepository->findAll();
        $allPartis = $partiRepository->findAll();

        // Map Parties by ID for easy access
        $partiMap = [];
        $partisFiltered = [];
        foreach ($allPartis as $p) {
            $partiMap[(string) $p->getId()] = $p;
            if (!in_array($p->getSigle(), ['BLANCS', 'NULS'])) {
                $partisFiltered[] = $p;
            }
        }

        // Initialize Global Stats
        $globalSeats = []; // [partiId => count]
        $comparativeData = []; // To be passed to JS [ {circo: "Name", results: {partiId: votes}} ]

        foreach ($partisFiltered as $p) {
            $globalSeats[(string) $p->getId()] = 0;
        }

        // Process data per Circonscription
        foreach ($circos as $circo) {
            $rawResults = $resultatRepository->findResultatsParCirconscription($circo->getId());

            // Format for SiegeCalculator
            $calcInput = [];
            $circoResultsMap = []; // [partiId => votes], for frontend table

            foreach ($rawResults as $row) {
                $pId = (string) $row['parti_id'];
                $votes = (int) $row['total_voix'];

                // Keep strictly valid parties for projection
                if (isset($partiMap[$pId]) && !in_array($partiMap[$pId]->getSigle(), ['BLANCS', 'NULS'])) {
                    $calcInput[] = [
                        'parti' => $partiMap[$pId],
                        'voix' => $votes
                    ];
                    $circoResultsMap[$pId] = $votes;
                }
            }

            // Calculate Seats
            $projection = $siegeCalculator->calculerSieges($calcInput, $circo->getSieges());

            // Aggregate Seats
            foreach ($projection['repartition'] as $pId => $info) {
                if (isset($globalSeats[$pId])) {
                    $globalSeats[$pId] += $info['total_sieges'];
                }
            }

            // Prepare Comparative Data Row
            $comparativeData[] = [
                'circo_nom' => $circo->getNom(),
                'circo_code' => $circo->getCode(),
                'sieges_total' => $circo->getSieges(),
                'votes' => $circoResultsMap
            ];
        }

        // Format Global Seat Data for View/Chart
        $parliamentData = [];
        foreach ($globalSeats as $pId => $seats) {
            if ($seats > 0 && isset($partiMap[$pId])) {
                $p = $partiMap[$pId];
                $parliamentData[] = [
                    'id' => $pId,
                    'sigle' => $p->getSigle(),
                    'nom' => $p->getNom(),
                    'color' => $p->getCouleur() ?? '#cccccc',
                    'seats' => $seats,
                    'affiliation' => $p->getAffiliation(),
                    'logo' => $uploaderHelper->asset($p, 'logoFile')
                ];
            }
        }

        // Sort by Affiliation (Left to Right: Opposition -> Coalition -> Mouvance)
        // Then by Seats (Largest at edges?) usually or just size.
        // User request: "opposition à gauches, coalition au centre, mouvance à droite".

        $affiliationOrder = [
            'OPPOSITION' => 1,
            'COALITION' => 2,
            'MOUVANCE' => 3
        ];

        usort($parliamentData, function ($a, $b) use ($affiliationOrder) {
            $affA = strtoupper($a['affiliation'] ?? '');
            $affB = strtoupper($b['affiliation'] ?? '');

            $orderA = $affiliationOrder[$affA] ?? 99;
            $orderB = $affiliationOrder[$affB] ?? 99;

            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            // Same affiliation: sort by seats descending (Largest parties first in block)
            return $b['seats'] <=> $a['seats'];
        });

        return $this->render('stats/parlement.html.twig', [
            'parlementData' => $parliamentData,
            'comparativeData' => $comparativeData,
            'partis' => $partisFiltered,
            'totalSeats' => array_sum($globalSeats)
        ]);
    }
}
