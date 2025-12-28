<?php

namespace App\Controller\Admin;

use App\Entity\BroadcastMessage;
use App\Entity\BureauDeVote;
use App\Entity\CentreDeVote;
use App\Entity\Circonscription;
use App\Entity\Election;
use App\Entity\Logs;
use App\Entity\Parti;
use App\Entity\Participation;
use App\Entity\Resultat;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Sure Vote <span class="text-benin-green">Bénin</span>')
            ->setFaviconPath('favicon.svg');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('css/admin.css');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Vue d’ensemble', 'fa fa-home');
        yield MenuItem::linkToCrud('Élections', 'fas fa-calendar-alt', Election::class);

        yield MenuItem::section('Structure Électorale');
        yield MenuItem::linkToCrud('Circonscriptions', 'fas fa-map-marker-alt', Circonscription::class);
        yield MenuItem::linkToCrud('Centres de Vote', 'fas fa-building', CentreDeVote::class);
        yield MenuItem::linkToCrud('Bureaux de Vote', 'fas fa-door-open', BureauDeVote::class);

        yield MenuItem::section('Compétiteurs');
        yield MenuItem::linkToCrud('Partis Politiques', 'fas fa-flag', Parti::class);

        yield MenuItem::section('Données de Vote');
        yield MenuItem::linkToCrud('Participation', 'fas fa-users', Participation::class);
        yield MenuItem::linkToCrud('Résultats PV', 'fas fa-file-invoice', Resultat::class);

        yield MenuItem::section('Configuration');
        yield MenuItem::linkToCrud('Utilisateurs & Assesseurs', 'fas fa-user-shield', User::class);

        yield MenuItem::section('Import');
        yield MenuItem::linkToUrl("Circonscriptions", "fas fa-file-import", '/admin/import/circonscriptions');
        yield MenuItem::linkToUrl("Centres de Vote", "fas fa-file-import", '/admin/import/centres-de-vote');

        yield MenuItem::section('Communication');
        yield MenuItem::linkToCrud('Messages (Diffusion)', 'fas fa-bullhorn', BroadcastMessage::class);

        yield MenuItem::section('Technique');
        yield MenuItem::linkToCrud('Logs Système', 'fas fa-history', Logs::class);

        yield MenuItem::linkToUrl('Retour au site', 'fas fa-globe', '/');
    }
}
