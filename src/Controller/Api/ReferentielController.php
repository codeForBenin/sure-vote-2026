<?php

namespace App\Controller\Api;

use App\Repository\BureauDeVoteRepository;
use App\Repository\CentreDeVoteRepository;
use App\Repository\CirconscriptionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/referentiel')]
class ReferentielController extends AbstractController
{
    #[Route('/circonscriptions', name: 'api_referentiel_circonscriptions', methods: ['GET'])]
    public function getCirconscriptions(CirconscriptionRepository $circonscriptionRepository): JsonResponse
    {
        $circonscriptions = $circonscriptionRepository->findBy([], ['nom' => 'ASC']);
        
        $data = [];
        foreach ($circonscriptions as $circonscription) {
            $data[] = [
                'id' => (string) $circonscription->getId(),
                'nom' => $circonscription->getNom(),
                'departement' => $circonscription->getDepartement(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/centres/{circonscriptionId}', name: 'api_referentiel_centres', methods: ['GET'])]
    public function getCentres(string $circonscriptionId, CentreDeVoteRepository $centreDeVoteRepository): JsonResponse
    {
        $centres = $centreDeVoteRepository->findBy(['circonscription' => $circonscriptionId], ['nom' => 'ASC']);

        $data = [];
        foreach ($centres as $centre) {
            $data[] = [
                'id' => (string) $centre->getId(),
                'nom' => $centre->getNom(),
                'adresse' => $centre->getAdresse(),
                'code' => $centre->getCode()
            ];
        }

        return $this->json($data);
    }

    #[Route('/bureaux/{centreId}', name: 'api_referentiel_bureaux', methods: ['GET'])]
    public function getBureaux(string $centreId, BureauDeVoteRepository $bureauDeVoteRepository): JsonResponse
    {
        $bureaux = $bureauDeVoteRepository->findBy(['centre' => $centreId], ['code' => 'ASC']);

        $data = [];
        foreach ($bureaux as $bureau) {
            $data[] = [
                'id' => (string) $bureau->getId(),
                'nom' => $bureau->getNom() ?? 'Bureau ' . $bureau->getCode(),
                'code' => $bureau->getCode()
            ];
        }

        return $this->json($data);
    }
}
