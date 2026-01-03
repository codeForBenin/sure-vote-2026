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
        if ($election?->getNombreInscrits()) {
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
            $pourcentageParticipation = floor(($totalVotants / $totalInscrits) * 100 * 10) / 10;
        }

        // 2. Calcul Bureaux Dépouillés (nombre absolu pour l'affichage public)
        $bureauxDepouilles = $this->resultatRepository->countBureauxAvecResultats();

        return $this->render('home/index.html.twig', [
            'stat_participation' => $pourcentageParticipation,
            'stat_depouilles' => $bureauxDepouilles,
            'total_bureaux' => $this->bureauDeVoteRepository->count([])
        ]);
    }

    #[Route('/pv-download/{id}', name: 'app_pv_download')]
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

    #[Route('/informations', name: 'app_infos')]
    public function infos(ElectionRepository $electionRepository): Response
    {
        $elections = $electionRepository->findAll();
        $election = $elections[0] ?? null;

        return $this->render('home/infos.html.twig', [
            'election' => $election
        ]);
    }

    #[Route('/stats/participation', name: 'app_stats_participation')]
    public function stats(
        ElectionRepository $electionRepository
    ): Response {
        $elections = $electionRepository->findAll();
        $election = $elections[0] ?? null;

        // Total Inscrits
        $totalInscrits = 0;
        if ($election?->getNombreInscrits()) {
            $totalInscrits = $election->getNombreInscrits();
        } else {
            $totalInscrits = $this->bureauDeVoteRepository->getTotalInscrits();
        }

        // Heures à analyser (08h à 18h)
        // On suppose que l'élection est CE JOUR pour l'affichage live, ou la date de l'élection
        $dateRef = new \DateTime('now', new \DateTimeZone('Africa/Porto-Novo'));
        if ($election) {
            $dateRef = \DateTime::createFromInterface($election->getDateElection());
            $dateRef->setTimezone(new \DateTimeZone('Africa/Porto-Novo'));
        }

        $hours = [];
        $data = [];
        $labels = [];

        // Points horaires : 08, 10, 12, 14, 16, 18
        $timePoints = [8, 10, 12, 14, 16, 18];

        foreach ($timePoints as $hour) {
            $checkTime = (clone $dateRef)->setTime($hour, 0);
            $now = new \DateTime('now', new \DateTimeZone('Africa/Porto-Novo'));

            // Si le point est dans le futur par rapport à "maintenant" (si c'est le jour même), on arrête ou on met null
            // Sauf si l'élection est passée
            if ($checkTime > $now && $checkTime->format('Y-m-d') === $now->format('Y-m-d')) {
                // Futur
                continue;
            }

            $count = $this->participationRepository->getVotesCountAtTime($checkTime);
            $rate = $totalInscrits > 0 ? round(($count / $totalInscrits) * 100, 2) : 0;

            $labels[] = $hour . 'H';
            $data[] = $rate;
        }

        return $this->render('home/stats.html.twig', [
            'labels' => $labels,
            'data' => $data,
            'election' => $election
        ]);
    }

    #[Route('/test-erreur', name: 'app_test_error')]
    public function testError(): Response
    {
        // Cela va déclencher l'événement kernel.exception
        throw new \Exception('Ceci est un test pour mon Subscriber !');
    }
}
