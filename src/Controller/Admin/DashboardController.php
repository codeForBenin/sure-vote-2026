<?php

namespace App\Controller\Admin;

use App\Entity\BroadcastMessage;
use App\Entity\BureauDeVote;
use App\Entity\CentreDeVote;
use App\Entity\Circonscription;
use App\Entity\Election;
use App\Entity\Logs;
use App\Entity\Observation;
use App\Entity\Parti;
use App\Entity\Participation;
use App\Entity\Resultat;
use App\Entity\SignalementInscrits;
use App\Entity\User;
use App\Repository\BureauDeVoteRepository;
use App\Repository\ObservationRepository;
use App\Repository\ParticipationRepository;
use App\Repository\ResultatRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;



#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private ObservationRepository $observationRepository,
        private BureauDeVoteRepository $bureauDeVoteRepository,
        private ParticipationRepository $participationRepository,
        private ResultatRepository $resultatRepository,
        private \App\Repository\ElectionRepository $electionRepository
    ) {
    }

    public function index(): Response
    {
        // 1. Observations
        $observations = $this->observationRepository->findBy(
            ['niveau' => 'URGENT'],
            ['createdAt' => 'DESC'],
            5
        );
        $urgentCount = $this->observationRepository->count(['niveau' => 'URGENT']);
        $totalObservations = $this->observationRepository->count([]);

        // 2. Calcul Participation Globale (Basé sur les résultats reçus + Participation en temps réel si dispo)
        // Récupération Inscrits Global (Election ou Somme Bureaux)
        $elections = $this->electionRepository->findAll();
        $election = $elections[0] ?? null;

        $totalInscrits = 0;
        if ($election && $election->getNombreInscrits()) {
            $totalInscrits = $election->getNombreInscrits();
        } else {
            $totalInscrits = $this->bureauDeVoteRepository->getTotalInscrits();
        }

        $totalVotantsResultats = $this->resultatRepository->getTotalVoix(); // Vrais votes comptés
        $totalVotantsParticipation = $this->participationRepository->getGlobalVotantsEstimate(); // Pointages assesseurs

        // On prend le max pour être le plus cohérent possible (si les assesseurs n'ont pas pointé mais ont envoyé les PV)
        $totalVotants = max($totalVotantsResultats, $totalVotantsParticipation);

        $pourcentageParticipation = 0;
        if ($totalInscrits > 0) {
            $pourcentageParticipation = round(($totalVotants / $totalInscrits) * 100, 1);
        }

        // 3. Calcul Bureaux Dépouillés
        $totalBureaux = $this->bureauDeVoteRepository->count([]);
        $bureauxDepouilles = $this->resultatRepository->countBureauxAvecResultats();

        $pourcentageDepouilles = 0;
        if ($totalBureaux > 0) {
            $pourcentageDepouilles = round(($bureauxDepouilles / $totalBureaux) * 100, 1);
        }

        // 4. Derniers Résultats
        $latestResults = $this->resultatRepository->findLatest(5);

        return $this->render('admin/dashboard.html.twig', [
            'urgent_alerts' => $observations,
            'urgent_count' => $urgentCount,
            'total_observations' => $totalObservations,
            'stat_participation' => $pourcentageParticipation,
            'stat_depouilles' => $pourcentageDepouilles,
            'latest_results' => $latestResults,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Sure Vote <span class="text-benin-green">Bénin</span>')
            ->setFaviconPath('/uploads/metadata/favicon.png');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('/css/admin.css');
    }

    public function configureMenuItems(): iterable
    {
        // yield MenuItem::linkToDashboard('Vue d’ensemble', 'fa fa-home');

        // Menu Spécial Superviseurs
        yield MenuItem::section('Validation & Accréditation')
            ->setPermission('ROLE_SUPERVISEUR');
        yield MenuItem::linkToCrud('File de Validation', 'fas fa-check-double', User::class)
            ->setController(ValidationUserCrudController::class)
            ->setPermission('ROLE_SUPERVISEUR');
        yield MenuItem::linkToCrud('Signalements Inscrits', 'fas fa-user', SignalementInscrits::class)
            ->setPermission('ROLE_SUPERVISEUR');
        yield MenuItem::linkToUrl('Envoyer un Email Interne', 'fas fa-envelope', '/admin/internal_email')
            ->setPermission('ROLE_SUPERVISEUR');

        // Administrateur Uniquement
        yield MenuItem::section('Gestion Élections')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('Élections', 'fas fa-calendar-alt', Election::class)->setPermission('ROLE_ADMIN');

        yield MenuItem::section('Terrain');
        yield MenuItem::linkToCrud('Observations Remontées', 'fas fa-eye', Observation::class); // Accessible Superviseur
        yield MenuItem::linkToCrud('Messages à Diffuser', 'fas fa-bullhorn', BroadcastMessage::class)->setPermission('ROLE_ADMIN');

        yield MenuItem::section('Structure Électorale')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('Circonscriptions', 'fas fa-map-marker-alt', Circonscription::class)->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('Centres de Vote', 'fas fa-building', CentreDeVote::class)->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('Bureaux de Vote', 'fas fa-door-open', BureauDeVote::class)->setPermission('ROLE_ADMIN');

        yield MenuItem::section('Compétiteurs')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('Partis Politiques', 'fas fa-flag', Parti::class)->setPermission('ROLE_ADMIN');

        yield MenuItem::section('Données de Vote');
        yield MenuItem::linkToCrud('Participation', 'fas fa-users', Participation::class)->setPermission('ROLE_ADMIN'); // Ou superviseur ?
        yield MenuItem::linkToCrud('Résultats PV', 'fas fa-file-invoice', Resultat::class)->setPermission('ROLE_ADMIN'); // La validation des résultats est admin

        yield MenuItem::section('Configuration')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('Utilisateurs (Admin)', 'fas fa-user-shield', User::class)->setPermission('ROLE_ADMIN')->setController(UserCrudController::class);

        yield MenuItem::section('Autres');
        yield MenuItem::linkToUrl('Logs ', 'fas fa-list', '/admin/logs')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToUrl('Retour au site', 'fas fa-globe', '/');
    }
}
