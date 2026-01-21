<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use League\Flysystem\FilesystemOperator;

use Symfony\Component\DependencyInjection\Attribute\Target;

#[Route('/admin/system')]
#[IsGranted('ROLE_ADMIN')]
class TestAzureController extends AbstractController
{
    #[Route('/test-azure', name: 'admin_test_azure')]
    public function index(#[Target('vote_reports.storage')] FilesystemOperator $voteReportsStorage, #[Target('party_logos.storage')] FilesystemOperator $partyLogosStorage, #[Target('observation_images.storage')] FilesystemOperator $observationImagesStorage): Response
    {
        $content = "Ceci est un test de connexion Azure depuis SureVote le " . date('Y-m-d H:i:s');
        $filename = 'test_connexion_' . time() . '.txt';
        $report = [];

        try {
            $report[] = "🟢 Tentative d'écriture [Conteneur PVS] : $filename";
            $voteReportsStorage->write($filename, $content);
            $report[] = "✅ Écriture réussie !";

            $report[] = "🟢 Vérification de l'existence...";
            if ($voteReportsStorage->fileExists($filename)) {
                $report[] = "✅ Le fichier existe bien sur le stockage distant (PVS).";
                $report[] = "🔗 URL Publique (PVS) : " . $voteReportsStorage->publicUrl($filename);
            } else {
                $report[] = "❌ Le fichier n'a pas été trouvé après écriture (latence ?).";
            }

            $report[] = "🟢 Tentative de suppression...";
            $voteReportsStorage->delete($filename);
            $report[] = "✅ Suppression réussie !";

            $statusPvs = "SUCCÈS";

        } catch (\Throwable $e) {
            $report[] = "❌ ERREUR [PVS]: " . $e->getMessage();
            $statusPvs = "ÉCHEC";
        }

        try {
            $report[] = "🟢 Tentative d'écriture [Conteneur LOGOS] : $filename";
            $partyLogosStorage->write($filename, $content);
            $report[] = "✅ Écriture réussie !";

            $report[] = "🟢 Vérification de l'existence...";
            if ($partyLogosStorage->fileExists($filename)) {
                $report[] = "✅ Le fichier existe bien sur le stockage distant (LOGOS).";
            } else {
                $report[] = "❌ Le fichier n'a pas été trouvé après écriture.";
            }

            $report[] = "🟢 Tentative de suppression...";
            $partyLogosStorage->delete($filename);
            $report[] = "✅ Suppression réussie !";

            $statusLogos = "SUCCÈS";

        } catch (\Throwable $e) {
            $report[] = "❌ ERREUR [LOGOS]: " . $e->getMessage();
            $statusLogos = "ÉCHEC";
        }

        try {
            $report[] = "🟢 Tentative d'écriture [Conteneur IMAGES] : $filename";
            $observationImagesStorage->write($filename, $content);
            $report[] = "✅ Écriture réussie !";

            $report[] = "🟢 Vérification de l'existence...";
            if ($observationImagesStorage->fileExists($filename)) {
                $report[] = "✅ Le fichier existe bien sur le stockage distant (IMAGES).";
            } else {
                $report[] = "❌ Le fichier n'a pas été trouvé après écriture.";
            }

            $report[] = "🟢 Tentative de suppression...";
            $observationImagesStorage->delete($filename);
            $report[] = "✅ Suppression réussie !";

            $statusImages = "SUCCÈS";

        } catch (\Throwable $e) {
            $report[] = "❌ ERREUR [IMAGES]: " . $e->getMessage();
            $statusImages = "ÉCHEC";
        }

        $globalStatus = ($statusPvs === "SUCCÈS" && $statusLogos === "SUCCÈS" && $statusImages === "SUCCÈS") ? "TOUT OK" : "ERREURS DÉTECTÉES";

        $envs = array_map(function ($key) {
            return [
                'key' => $key,
                'value' => $_ENV[$key]
            ];
        }, array_keys($_ENV));

        return $this->render('admin/system/test_azure.html.twig', [
            'report' => $report,
            'status' => $globalStatus,
            'envs' => $envs
        ]);
    }
}
