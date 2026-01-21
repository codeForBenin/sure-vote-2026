<?php

namespace App\Controller;

use App\Repository\LogsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\Request;

class AuditPublicController extends AbstractController
{
    #[Route('/transparence/audit', name: 'app_public_audit')]
    public function index(LogsRepository $logsRepository, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;

        // On récupère les logs paginés
        $logs = $logsRepository->findPublicLogs($page, $limit);
        $totalLogs = count($logs); // Paginator implemente Countable pour le TOTAL
        $maxPages = ceil($totalLogs / $limit);

        return $this->render('home/audit.html.twig', [
            'logs' => $logs,
            'currentPage' => $page,
            'maxPages' => $maxPages
        ]);
    }
}
