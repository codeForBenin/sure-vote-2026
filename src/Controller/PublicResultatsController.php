<?php

namespace App\Controller;

use App\Entity\BureauDeVote;
use App\Repository\BureauDeVoteRepository;
use App\Repository\ResultatRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/resultats-public')]
class PublicResultatsController extends AbstractController
{
    #[Route('/', name: 'app_public_resultats_search')]
    public function search(Request $request, BureauDeVoteRepository $bureauDeVoteRepository): Response
    {
        $query = $request->query->get('q');
        $bureaux = [];

        if ($query) {
            $bureaux = $bureauDeVoteRepository->search($query);
        }

        return $this->render('public_resultats/search.html.twig', [
            'query' => $query,
            'bureaux' => $bureaux,
        ]);
    }

    #[Route('/suggestions', name: 'app_public_resultats_suggestions')]
    public function suggestions(Request $request, BureauDeVoteRepository $bureauDeVoteRepository): Response
    {
        $query = $request->query->get('q');

        if (!$query || strlen($query) < 2) {
            return new Response('');
        }

        $bureaux = $bureauDeVoteRepository->search($query);
        $bureaux = array_slice($bureaux, 0, 5);

        return $this->render('public_resultats/_suggestions.html.twig', [
            'bureaux' => $bureaux
        ]);
    }

    #[Route('/bureau/{id}', name: 'app_public_resultats_show')]
    public function show(BureauDeVote $bureau, ResultatRepository $resultatRepository): Response
    {
        // Récupérer les résultats pour ce bureau
        // On suppose que l'on veut afficher tous les résultats, ou peut-être agrégés par parti.
        // Comme Resultat stocke (bureau, parti, voix), on peut récupérer la liste.

        $resultats = $resultatRepository->findBy(['bureauDeVote' => $bureau]);

        // On récupère le PV s'il existe (il est lié au Résultat, mais potentiellement chaque résultat a le même PV ou lien)
        // Généralement un PV est pour tout le bureau. Dans notre modèle Resultat a pvImageName.
        // On suppose que s'il y a un PV, il est accessible via l'un des résultats.
        $pvImage = null;
        foreach ($resultats as $res) {
            if ($res->getPvImageName()) {
                $pvImage = $res->getPvImageName();
                break;
            }
        }

        return $this->render('public_resultats/show.html.twig', [
            'bureau' => $bureau,
            'resultats' => $resultats,
            'pvImage' => $pvImage
        ]);
    }
}
