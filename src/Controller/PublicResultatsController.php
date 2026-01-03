<?php

namespace App\Controller;

use App\Entity\BureauDeVote;
use App\Repository\BureauDeVoteRepository;
use App\Repository\CentreDeVoteRepository;
use App\Repository\ResultatRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/resultats-public')]
class PublicResultatsController extends AbstractController
{
    #[Route('/', name: 'app_public_resultats_search')]
    public function search(Request $request, CentreDeVoteRepository $centreDeVoteRepository): Response
    {
        $query = $request->query->get('q');
        $centres = [];

        if ($query) {
            $centres = $centreDeVoteRepository->search($query);
        }

        return $this->render('public_resultats/search.html.twig', [
            'query' => $query,
            'centres' => $centres,
        ]);
    }

    #[Route('/suggestions', name: 'app_public_resultats_suggestions')]
    public function suggestions(Request $request, CentreDeVoteRepository $centreDeVoteRepository): Response
    {
        $query = $request->query->get('q');

        if (!$query || strlen($query) < 2) {
            return new Response('');
        }

        $centres = $centreDeVoteRepository->search($query);
        $centres = array_slice($centres, 0, 5);

        return $this->render('public_resultats/_suggestions.html.twig', [
            'centres' => $centres
        ]);
    }

    #[Route('/bureau/{id}', name: 'app_public_resultats_show')]
    public function show(BureauDeVote $bureau, ResultatRepository $resultatRepository): Response
    {
        // Récupérer les résultats pour ce bureau
        // On suppose que l'on veut afficher tous les résultats, ou peut-être agrégés par parti.
        // Comme Resultat stocke (bureau, parti, voix), on peut récupérer la liste.

        $resultats = $resultatRepository->findBy(['bureauDeVote' => $bureau]);

        // On récupère le PV s'il existe (il est lié au Résultat)
        $resultatWithPv = null;
        foreach ($resultats as $res) {
            if ($res->getPvImageName()) {
                $resultatWithPv = $res;
                break;
            }
        }

        return $this->render('public_resultats/show.html.twig', [
            'bureau' => $bureau,
            'resultats' => $resultats,
            'resultatWithPv' => $resultatWithPv
        ]);
    }
}
