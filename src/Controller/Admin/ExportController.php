<?php

namespace App\Controller\Admin;

use App\Repository\LogsRepository;
use App\Repository\ParticipationRepository;
use App\Repository\ResultatRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/export')]
class ExportController extends AbstractController
{
    #[Route('/vote-participation', name: 'app_admin_participation_export')]
    public function exportParticipations(ParticipationRepository $repository, LogsRepository $logsRepository, RequestStack $requestStack): Response
    {
        // LOG EXPORT
        $logsRepository->logAction(
            action: 'EXPORT_PARTICIPATION',
            user: $this->getUser(),
            ip: $requestStack->getCurrentRequest()->getClientIp(),
            userAgent: $requestStack->getCurrentRequest()->headers->get('User-Agent')
        );

        $participations = $repository->findAll();

        $response = new StreamedResponse(function () use ($participations) {
            $handle = fopen('php://output', 'w+');

            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Header
            fputcsv($handle, ['ID', 'Bureau de Vote', 'Assesseur', 'Heure Pointage', 'Nombre Votants', 'Latitude', 'Longitude'], ';');

            foreach ($participations as $participation) {
                $bureau = $participation->getBureauDeVote();
                $assesseur = $participation->getAssesseur();
                $location = $participation->getMetadataLocation();

                fputcsv($handle, [
                    $participation->getId(),
                    $bureau ? $bureau->getNom() : '',
                    $assesseur ? $assesseur->getNom() . ' ' . $assesseur->getPrenom() : '',
                    $participation->getHeurePointage()->format('d/m/Y H:i:s'),
                    $participation->getNombreVotants(),
                    $location['latitude'] ?? '',
                    $location['longitude'] ?? ''
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="participations.csv"');

        return $response;
    }

    #[Route('/vote-resultat', name: 'app_admin_resultats_export')]
    public function exportResultats(ResultatRepository $repository, UrlGeneratorInterface $urlGenerator, LogsRepository $logsRepository, RequestStack $requestStack): Response
    {
        // LOG EXPORT
        $logsRepository->logAction(
            action: 'EXPORT_RESULTAT',
            user: $this->getUser(),
            ip: $requestStack->getCurrentRequest()->getClientIp(),
            userAgent: $requestStack->getCurrentRequest()->headers->get('User-Agent')
        );

        $resultats = $repository->findAll();

        $response = new StreamedResponse(function () use ($resultats, $urlGenerator) {
            $handle = fopen('php://output', 'w+');

            // UTF-8 BOM for Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, [
                'ID',
                'Bureau de Vote',
                'Code Bureau',
                'Circonscription',
                'Parti Politique',
                'Sigle Parti',
                'Nombre de Voix',
                'Assesseur',
                'Email Assesseur',
                'Validé',
                'Image PV'
            ], ';');

            // Data rows
            foreach ($resultats as $resultat) {
                $bureau = $resultat->getBureauDeVote();
                $parti = $resultat->getParti();
                $assesseur = $resultat->getAssesseur();

                $pvImageUrl = $resultat->getPvImageName()
                    ? $urlGenerator->generate('app_pv_download', ['id' => $resultat->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
                    : '';

                fputcsv($handle, [
                    $resultat->getId(),
                    $bureau ? $bureau->getNom() : '',
                    $bureau ? $bureau->getCode() : '',
                    $bureau && $bureau->getCentre() ? $bureau->getCentre()->getCirconscription()->getNom() : '',
                    $parti ? $parti->getNom() : '',
                    $parti ? $parti->getSigle() : '',
                    $resultat->getNombreVoix(),
                    $assesseur ? $assesseur->getNom() . ' ' . $assesseur->getPrenom() : '',
                    $assesseur ? $assesseur->getEmail() : '',
                    $resultat->isIsValidated() ? 'Oui' : 'Non',
                    $pvImageUrl
                ], ';');
            }

            fclose($handle);
        });

        $randomString = bin2hex(random_bytes(5));
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="resultats_sure_vote_du_' . date('d-m-Y_His') . '_' . $randomString . '.csv"');

        return $response;
    }

}
