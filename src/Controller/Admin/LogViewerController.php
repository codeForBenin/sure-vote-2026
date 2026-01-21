<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request;

#[Route('/admin/system')]
#[IsGranted('ROLE_ADMIN')]
class LogViewerController extends AbstractController
{
    #[Route('/logs', name: 'admin_system_logs')]
    public function index(Request $request): Response
    {
        $env = $this->getParameter('kernel.environment');
        $logDir = $this->getParameter('kernel.logs_dir');
        $logFile = $logDir . '/' . $env . '.log';

        $content = [];

        if (file_exists($logFile)) {
            // Lecture des dernières lignes (similaire à tail -n 200)
            $file = file($logFile);
            $lines = array_slice($file, -200); // 200 dernières lignes
            $content = array_reverse($lines); // Plus récent en haut
        } else {
            $content = ["Fichier de log introuvable : " . $logFile];
        }
        $date = date('Y-m-d H:i:s');

        return $this->render('admin/system/logs.html.twig', [
            'logs' => $content,
            'logPath' => $logFile,
            'env' => $env,
            'date_du_serveur' => $date
        ]);
    }
}
