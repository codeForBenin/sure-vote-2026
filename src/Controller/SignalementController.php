<?php

namespace App\Controller;

use App\Entity\CentreDeVote;
use App\Entity\SignalementInscrits;
use App\Form\SignalementInscritsType;
use App\Repository\CentreDeVoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class SignalementController extends AbstractController
{
    #[Route('/signalement-inscrits', name: 'app_signalement_inscrits')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $signalement = new SignalementInscrits();
        $form = $this->createForm(SignalementInscritsType::class, $signalement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupération manuelle du centre
            $centreId = $form->get('centreId')->getData();
            if ($centreId) {
                $centre = $em->getRepository(CentreDeVote::class)->find($centreId);
                if ($centre) {
                    $signalement->setCentreDeVote($centre);
                } else {
                    $this->addFlash('error', 'Centre de vote invalide.');
                    return $this->render('signalement/index.html.twig', ['form' => $form->createView()]);
                }
            } else {
                $this->addFlash('error', 'Veuillez sélectionner un centre de vote.');
                return $this->render('signalement/index.html.twig', ['form' => $form->createView()]);
            }

            // Traitement du champ JSON "mocké"
            $jsonBureaux = $form->get('repartitionBureauxJson')->getData();
            if ($jsonBureaux) {
                $data = json_decode($jsonBureaux, true);
                if (is_array($data)) {
                    $signalement->setRepartitionBureaux($data);
                }
            }

            // Si le total est 0 mais que le détail est rempli, on peut recalculer le total
            if ($signalement->getNombreInscritsTotal() == 0 && !empty($signalement->getRepartitionBureaux())) {
                $total = 0;
                foreach ($signalement->getRepartitionBureaux() as $b) {
                    $total += (int) ($b['inscrits'] ?? 0);
                }
                $signalement->setNombreInscritsTotal($total);
            }

            $em->persist($signalement);
            $em->flush();

            $this->addFlash('success', 'Merci ! Votre signalement a été enregistré et sera vérifié.');

            return $this->redirectToRoute('app_home'); // Ou retour au même form
        }

        return $this->render('signalement/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/api/search/centres', name: 'api_search_centres', methods: ['GET'])]
    public function searchCentres(Request $request, CentreDeVoteRepository $repo): JsonResponse
    {
        $query = $request->query->get('q');
        if (!$query || strlen($query) < 2) {
            return $this->json([]);
        }

        // On assume que le repository a une méthode de recherche ou on utilise le QueryBuilder directement ici
        // Pour faire simple et robuste :
        $centres = $repo->createQueryBuilder('c')
            ->where('LOWER(c.nom) LIKE :query')
            ->orWhere('LOWER(c.commune) LIKE :query')
            ->orWhere('LOWER(c.arrondissement) LIKE :query')
            ->setParameter('query', '%' . strtolower($query) . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($centres as $c) {
            $results[] = [
                'id' => $c->getId(),
                'nom' => $c->getNom(),
                'location' => $c->getArrondissement() . ' - ' . $c->getCommune()
            ];
        }

        return $this->json($results);
    }

    #[Route('/api/centre/{id}/bureaux', name: 'api_centre_bureaux', methods: ['GET'])]
    public function getBureaux(CentreDeVote $centre): JsonResponse
    {
        $bureaux = [];
        foreach ($centre->getBureaux() as $bureau) {
            $bureaux[] = [
                'id' => $bureau->getId(),
                'nom' => $bureau->getNom(), // "Poste X" normalement
                'code' => $bureau->getCode()
            ];
        }

        return $this->json($bureaux);
    }
}
