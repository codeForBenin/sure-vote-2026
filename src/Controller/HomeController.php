<?php

namespace App\Controller;

use App\Entity\Resultat;
use App\Repository\LogsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
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
}
