<?php

namespace App\Controller;

use App\Entity\Resultat;
use App\Repository\LogsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use App\Repository\BureauDeVoteRepository;
use App\Repository\ParticipationRepository;
use App\Repository\ResultatRepository;
use App\Repository\ElectionRepository;

class HomeController extends AbstractController
{
    public function __construct(
        private BureauDeVoteRepository $bureauDeVoteRepository,
        private ParticipationRepository $participationRepository,
        private ResultatRepository $resultatRepository
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function index(ElectionRepository $electionRepository): Response
    {
        // 1. Calcul Participation Globale
        $elections = $electionRepository->findAll();
        $election = $elections[0] ?? null;

        $totalInscrits = 0;
        if ($election && $election->getNombreInscrits()) {
            $totalInscrits = $election->getNombreInscrits();
        } else {
            $totalInscrits = $this->bureauDeVoteRepository->getTotalInscrits();
        }

        $totalVotantsResultats = $this->resultatRepository->getTotalVoix();
        $totalVotantsParticipation = $this->participationRepository->getGlobalVotantsEstimate();

        // On prend le max pour être cohérent avec l'admin
        $totalVotants = max($totalVotantsResultats, $totalVotantsParticipation);

        $pourcentageParticipation = 0;
        if ($totalInscrits > 0) {
            $pourcentageParticipation = round(($totalVotants / $totalInscrits) * 100, 1);
        }

        // 2. Calcul Bureaux Dépouillés (nombre absolu pour l'affichage public)
        $bureauxDepouilles = $this->resultatRepository->countBureauxAvecResultats();

        return $this->render('home/index.html.twig', [
            'stat_participation' => $pourcentageParticipation,
            'stat_depouilles' => $bureauxDepouilles,
        ]);
    }

    #[Route('/handle-pv-download/{id}', name: 'app_handle_pv_download')]
    public function handlePvDownload($id, EntityManagerInterface $entityManagerInterface, RequestStack $requestStack, LogsRepository $logsRepository): Response
    {
        $resultat = $entityManagerInterface->getRepository(Resultat::class)->find($id);
        if (!$resultat) {
            throw $this->createNotFoundException('Resultat non trouvé');
        }

        $file = $this->getParameter('kernel.project_dir') . '/public/uploads/pv/' . $resultat->getPvImageName();
        if (!file_exists($file)) {
            throw $this->createNotFoundException('Fichier non trouvé');
        }

        $request = $requestStack->getCurrentRequest();

        // Log l'action de téléchargement
        $logsRepository->logAction(
            action: 'PV_DOWNLOAD',
            user: $this->getUser(),
            ip: $request->getClientIp(),
            userAgent: $request->headers->get('User-Agent'),
            details: [
                'filename' => $resultat->getPvImageName(),
                'resultat_id' => (string) $resultat->getId(),
                'bureau' => $resultat->getBureauDeVote() ? ($resultat->getBureauDeVote()->getNom() . ' (' . $resultat->getBureauDeVote()->getCode() . ')') : 'N/A'
            ]
        );

        return $this->file($file);
    }


    #[Route('/projections', name: 'app_projections')]
    public function projections(): Response
    {
        return $this->render('projections/index.html.twig');
    }
}
