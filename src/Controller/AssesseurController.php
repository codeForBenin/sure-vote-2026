<?php

namespace App\Controller;

use App\Entity\BureauDeVote;
use App\Entity\Observation;
use App\Entity\Parti;
use App\Entity\Participation;
use App\Entity\Resultat;
use App\Entity\User;
use App\Form\ObservationType;
use App\Form\ParticipationType;
use App\Form\ResultatsType;
use App\Repository\BroadcastMessageRepository;
use App\Repository\BureauDeVoteRepository;
use App\Repository\ElectionRepository;
use App\Repository\LogsRepository;
use App\Repository\ObservationRepository;
use App\Repository\PartiRepository;
use App\Repository\ParticipationRepository;
use App\Repository\ResultatRepository;
use App\Service\GeoVerifier;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use App\Form\GlobalResultatsType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Handler\UploadHandler;

#[Route('/assesseur')]
class AssesseurController extends AbstractController
{
    #[Route('/', name: 'app_assesseur_dashboard')]
    public function index(
        ResultatRepository $resultatRepository,
        ParticipationRepository $participationRepository,
        BroadcastMessageRepository $broadcastMessageRepository,
        ElectionRepository $electionRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $assignedCentre = $user->getAssignedCentre();

        $bureaux = [];
        $isSupervisor = false; // Tout le monde est "un peu" superviseur de son centre maintenant, ou on garde le role pour d'autres droits ?
        // On considère que tout le monde voit la liste des bureaux de son centre.

        if ($assignedCentre) {
            // Check role if needed, or just assuming assignedCentre means access to its bureaux
            $bureaux = $assignedCentre->getBureaux()->toArray();

            // Si on veut garder la distinction visuelle "Superviseur"
            if ($this->isGranted('ROLE_SUPERVISEUR')) {
                $isSupervisor = true;
            }
        } else {
            if ($this->isGranted('ROLE_ADMIN')) {
                $this->addFlash('warning', 'Vous êtes administrateur sans centre assigné. Veuillez en assigner un ou utiliser le dashboard admin.');
                return $this->redirectToRoute('admin');
            }
            throw $this->createAccessDeniedException('Aucun centre de vote ne vous est assigné. Contactez l\'administrateur.');
        }

        // --- Gestion de la plage horaire ---
        $elections = $electionRepository->findAll();
        $election = $elections[0] ?? null;
        $isSaisieOuverte = true;

        if ($election && $election->getHeureFermeture()) {
            $dateElection = $election->getDateElection();
            $heureFermeture = $election->getHeureFermeture();
            $fermetureDateTime = \DateTime::createFromFormat(
                'Y-m-d H:i:s',
                $dateElection->format('Y-m-d') . ' ' . $heureFermeture->format('H:i:s'),
                new \DateTimeZone('Africa/Porto-Novo')
            );
            $limiteSaisie = (clone $fermetureDateTime)->modify('+4 hours'); // Tolérance élargie (4h)
            $now = new \DateTime('now', new \DateTimeZone('Africa/Porto-Novo'));
            if ($now > $limiteSaisie && $this->getParameter('kernel.environment') !== 'dev') {
                $isSaisieOuverte = false;
            }
        }

        // Calculer les stats pour chaque bureau
        $bureauxStats = [];
        foreach ($bureaux as $bureau) {
            $sommeVoix = $resultatRepository->getSommeVoixParBureau((string) $bureau->getId());
            $isVoteComplet = ($sommeVoix >= $bureau->getNombreInscrits()) && $bureau->getNombreInscrits() > 0;

            $latestParticipation = $participationRepository->findLatestByBureau($bureau->getId());
            $nombreVotants = $latestParticipation ? $latestParticipation->getNombreVotants() : 0;
            $nombreVotants = max($nombreVotants, $sommeVoix);

            $tauxParticipation = 0;
            if ($bureau->getNombreInscrits() > 0) {
                $tauxParticipation = ($nombreVotants / $bureau->getNombreInscrits()) * 100;
            }

            // Resultats saisis ?
            $resultats = $resultatRepository->findBy(['bureauDeVote' => $bureau], ['nombreVoix' => 'DESC']);
            $isSaisi = count($resultats) > 0;

            $bureauxStats[] = [
                'entity' => $bureau,
                'sommeVoix' => $sommeVoix,
                'isVoteComplet' => $isVoteComplet,
                'nombreVotants' => $nombreVotants,
                'tauxParticipation' => floor($tauxParticipation * 100) / 100,
                'isSaisi' => $isSaisi,
                'detailsResultats' => $resultats
            ];
        }

        // Récupération des messages
        $messages = $broadcastMessageRepository->findActiveMessages();

        return $this->render('assesseur/index.html.twig', [
            'isSupervisor' => $isSupervisor,
            'bureauxStats' => $bureauxStats, // Liste de stats
            'messages' => $messages,
            'election' => $election,
            'isSaisieOuverte' => $isSaisieOuverte,
            // Pour compatibilité template
            'singleBureauStat' => null
        ]);
    }

    #[Route('/participation/{id}', name: 'app_assesseur_participation', methods: ['GET', 'POST'])]
    public function participation(
        Request $request,
        EntityManagerInterface $em,
        GeoVerifier $geoVerifier,
        LoggerInterface $logger,
        LogsRepository $logsRepository,
        string $id
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $assignedCentre = $user->getAssignedCentre();

        $managedBureau = $em->getRepository(BureauDeVote::class)->find($id);

        if (!$managedBureau) {
            throw $this->createNotFoundException('Bureau introuvable');
        }

        // Check Access: Bureau must belong to user's assigned centre
        if (!$assignedCentre || $managedBureau->getCentre()->getId() !== $assignedCentre->getId()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce bureau.');
        }

        $participation = new Participation();
        // Set bureau and assesseur BEFORE form handling so the validator can access them
        $participation->setBureauDeVote($managedBureau);
        $participation->setAssesseur($user);

        $logger->info('Bureau de vote: ' . $managedBureau->getNom());
        $logger->info('Assesseur: ' . $user->getNom() . ' (' . $user->getEmail() . ')');


        $form = $this->createForm(ParticipationType::class, $participation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Validation: Nombre de votants <= Nombre inscrits
            if ($participation->getNombreVotants() > $managedBureau->getNombreInscrits()) {
                $form->get('nombreVotants')->addError(new FormError(
                    sprintf(
                        'Le nombre de votants (%d) ne peut pas dépasser le nombre d\'inscrits (%d).',
                        $participation->getNombreVotants(),
                        $managedBureau->getNombreInscrits()
                    )
                ));
            }

            if ($form->isValid()) { // Re-check valid after custom errors
                // Set heurePointage après validation pour s'assurer qu'il est le moment exact de la soumission
                $participation->setHeurePointage(new \DateTimeImmutable());

                // Debug: vérifier si bureau et assesseur sont bien remplis
                if (!$participation->getBureauDeVote()) {
                    throw new \Exception('Bureau de vote is null after form handling!');
                }
                if (!$participation->getAssesseur()) {
                    throw new \Exception('Assesseur is null after form handling!');
                }

                // Vérification Géolocalisation
                $lat = $request->request->get('lat');
                $lon = $request->request->get('lon');

                if ($lat && $lon) {
                    $centre = $managedBureau->getCentre();
                    $isNear = null;

                    if ($centre && $centre->getLatitude() !== null && $centre->getLongitude() !== null) {
                        $isNear = $geoVerifier->isWithinRange(
                            (float) $lat,
                            (float) $lon,
                            $centre->getLatitude(),
                            $centre->getLongitude()
                        );
                    }

                    $participation->setMetadataLocation([
                        'lat' => $lat,
                        'lon' => $lon,
                        'is_near' => $isNear
                    ]);
                }

                $logger->info((string) $participation);

                // --- FIX DOCTRINE DETACHED ENTITY ---
                // On efface pour nettoyer l'unité de travail
                $em->clear();

                // On recharge les entités "fraîches"
                $freshBureau = $em->find(BureauDeVote::class, $managedBureau->getId());
                $freshUser = $em->find(User::class, $user->getId());

                if (!$freshBureau || !$freshUser) {
                    // Should not happen unless concurrent deleted
                    throw new \Exception("Erreur critique: Impossible de recharger les entités.");
                }

                // On recrée l'objet Participation avec ces entités fraîches
                $freshParticipation = new Participation();
                $freshParticipation->setBureauDeVote($freshBureau);
                $freshParticipation->setAssesseur($freshUser);
                $freshParticipation->setNombreVotants($participation->getNombreVotants());
                $freshParticipation->setHeurePointage($participation->getHeurePointage());
                $freshParticipation->setMetadataLocation($participation->getMetadataLocation());

                $em->persist($freshParticipation);
                $em->flush();

                $logsRepository->logAction(
                    "PARTICIPATION_ENREGISTRE",
                    $freshUser,
                    $request->getClientIp(),
                    $request->headers->get("User-Agent"),
                    [
                        "bureauDeVote" => $freshBureau->getNom(),
                        "assesseur" => $freshUser->getNom(),
                        "nombreVotants" => $participation->getNombreVotants(),
                        "heurePointage" => $participation->getHeurePointage()->format('d/m/Y H:i:s'),
                        "metadataLocation" => $participation->getMetadataLocation()
                    ]
                );

                $this->addFlash('success', 'Pointage de participation enregistré !');
                return $this->redirectToRoute('app_assesseur_dashboard');
            }
        }

        return $this->render('assesseur/participation.html.twig', [
            'form' => $form,
            'bureau' => $managedBureau,
        ]);
    }

    /**
     * Pointage des résultats
     */
    #[Route('/saisie-resultats/{id}', name: 'app_assesseur_saisie_resultats', methods: ['GET', 'POST'])]
    public function saisieGlobale(
        Request $request,
        EntityManagerInterface $em,
        GeoVerifier $geoVerifier,
        ResultatRepository $resultatRepository,
        PartiRepository $partiRepository,
        BureauDeVoteRepository $bureauDeVoteRepository,
        ElectionRepository $electionRepository,
        UploadHandler $uploadHandler,
        LogsRepository $logsRepository,
        string $id
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user)
            return $this->redirectToRoute('app_login');

        // Resolve Bureau
        $assignedCentre = $user->getAssignedCentre();

        $managedBureau = $bureauDeVoteRepository->find($id);
        if (!$managedBureau) {
            throw $this->createNotFoundException('Bureau introuvable.');
        }

        // Access Check
        if (!$assignedCentre || $managedBureau->getCentre()->getId() !== $assignedCentre->getId()) {
            throw $this->createAccessDeniedException('Accès refusé pour ce bureau.');
        }


        // Vérification Période Saisie
        $elections = $electionRepository->findAll();
        $election = $elections[0] ?? null;
        $isSaisieOuverte = true;

        if ($election && $election->getHeureFermeture()) {
            $dateElection = $election->getDateElection();
            $heureFermeture = $election->getHeureFermeture();

            $fermetureDateTime = \DateTime::createFromFormat(
                'Y-m-d H:i:s',
                $dateElection->format('Y-m-d') . ' ' . $heureFermeture->format('H:i:s'),
                new \DateTimeZone('Africa/Porto-Novo')
            );
            $limiteSaisie = (clone $fermetureDateTime)->modify('+4 hours');
            $now = new \DateTime('now', new \DateTimeZone('Africa/Porto-Novo'));
            if ($now > $limiteSaisie) {
                $isSaisieOuverte = false;
            }
        }

        // DEV OVERRIDE
        if ($this->getParameter('kernel.environment') === 'dev') {
            $isSaisieOuverte = true;
        }

        if (!$isSaisieOuverte) {
            $this->addFlash('error', 'La saisie des résultats est fermée.');
            return $this->redirectToRoute('app_assesseur_dashboard');
        }

        // Chargement Données
        $partis = $partiRepository->findAll();
        $resultatsExistants = $resultatRepository->findBy(['bureauDeVote' => $managedBureau]);

        // Préparation Data Form
        $formData = [];
        $existingPvName = null;
        $resultatWithPv = null;

        foreach ($resultatsExistants as $res) {
            $formData['parti_' . $res->getParti()->getId()] = $res->getNombreVoix();
            if ($res->getPvImageName()) {
                $existingPvName = $res->getPvImageName();
                $resultatWithPv = $res;
            }
        }

        $form = $this->createForm(GlobalResultatsType::class, $formData, [
            'partis' => $partis
        ]);

        // Si une image existe déjà, on enlève la contrainte Required du champ file
        if ($existingPvName) {
            $form->get('pvImageFile')->getConfig()->getOptions()['required'] = false; // Note: Constraints remain but 'required' attribute helps frontend
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // 1. SECURITY: Geolocation Check
            $lat = $request->request->get('lat');
            $lon = $request->request->get('lon');
            $centre = $managedBureau->getCentre();

            // Bypass en dev local si lat/lon absents
            $isDev = $this->getParameter('kernel.environment') === 'dev';

            if ((!$lat || !$lon) && !$isDev) {
                $this->addFlash('error', 'Erreur de géolocalisation: Coordonnées manquantes. Activez votre GPS.');
                return $this->redirectToRoute('app_assesseur_saisie_resultats', ['id' => $id]);
            }

            if ($lat && $lon && $centre && $centre->getLatitude() && $centre->getLongitude()) {
                $isNear = $geoVerifier->isWithinRange(
                    (float) $lat,
                    (float) $lon,
                    $centre->getLatitude(),
                    $centre->getLongitude()
                );

                if (!$isNear && !$isDev) {
                    // LOG FRAUDE POTENTIELLE
                    $logsRepository->logAction(
                        "FRAUDE_TENTATIVE_DISTANCE",
                        $user,
                        $request->getClientIp(),
                        $request->headers->get("User-Agent"),
                        ['bureau' => $managedBureau->getNom(), 'lat' => $lat, 'lon' => $lon]
                    );

                    $this->addFlash('error', '⛔ Position non valide : Vous êtes trop loin du centre de vote pour saisir les résultats.');
                    return $this->redirectToRoute('app_assesseur_saisie_resultats', ['id' => $id]);
                }
            }

            // Validation: Total vs Inscrits
            $totalVoixSaisies = 0;
            foreach ($partis as $parti) {
                $totalVoixSaisies += (int) $form->get('parti_' . $parti->getId())->getData();
            }

            if ($totalVoixSaisies > $managedBureau->getNombreInscrits()) {
                $form->addError(new FormError(sprintf(
                    'Le total des voix (%d) dépasse le nombre d\'inscrits (%d). Impossible de valider.',
                    $totalVoixSaisies,
                    $managedBureau->getNombreInscrits()
                )));
                return $this->render('assesseur/saisie_globale.html.twig', [
                    'form' => $form->createView(),
                    'managedBureau' => $managedBureau,
                    'existingPvName' => $existingPvName,
                    'resultatWithPv' => $resultatWithPv
                ]);
            }

            // Gestion Upload
            $pvFile = $form->get('pvImageFile')->getData();
            $newFilename = $existingPvName;

            // Vérification si aucun fichier n'existe et aucun n'est envoyé
            if (!$pvFile && !$existingPvName) {
                $this->addFlash('error', 'Vous devez uploader une photo du PV.');
                return $this->redirectToRoute('app_assesseur_saisie_resultats', ['id' => $id]);
            }

            $em->getConnection()->beginTransaction();
            try {
                // 1. Verrouillage / Reload des dépendances
                // On utilise PESSIMISTIC_WRITE sur le bureau pour éviter les accès concurrents
                $bureauId = $managedBureau->getId();
                $em->clear();

                $freshBureau = $em->find(BureauDeVote::class, $bureauId, LockMode::PESSIMISTIC_WRITE);
                $freshUser = $em->find(User::class, $user->getId());

                if (!$freshBureau)
                    throw new \Exception("Bureau introuvable lors de la transaction.");

                // 2. Traitement des Votes
                $freshPartis = $partiRepository->findAll(); // Reload partis
                $freshResultatsExistants = $resultatRepository->findBy(['bureauDeVote' => $freshBureau]);

                // Identifier le premier resultat pour l'upload
                $firstResultatForUpload = null;
                $resultatsToProcess = [];

                foreach ($freshPartis as $parti) {
                    $fieldName = 'parti_' . $parti->getId();
                    // Note: Le formulaire n'est pas "relié" à l'upload dans la transaction, on utilise les données récupérées avant le clear()
                    // MAIS $form->getData() contient les valeurs soumises. Le clear() ne vide pas l'objet Form PHP en mémoire.
                    $voix = $form->get($fieldName)->getData();

                    $resultat = null;
                    foreach ($freshResultatsExistants as $existing) {
                        if ($existing->getParti()->getId() === $parti->getId()) {
                            $resultat = $existing;
                            break;
                        }
                    }

                    if (!$resultat) {
                        $resultat = new Resultat();
                        $resultat->setBureauDeVote($freshBureau); // Relation maitrisée
                        $resultat->setParti($parti);
                    }

                    $resultat->setAssesseur($freshUser);
                    $resultat->setNombreVoix((int) $voix);
                    $resultat->setUpdatedAt(new \DateTimeImmutable());

                    $resultatsToProcess[] = $resultat;

                    // On garde le premier pour l'upload
                    if ($pvFile && !$firstResultatForUpload) {
                        $firstResultatForUpload = $resultat;
                    }
                }

                // 3. Gestion de l'upload (si nouveau fichier)
                if ($pvFile && $firstResultatForUpload) {
                    // Mettre à jour le fichier
                    $firstResultatForUpload->setPvImageFile($pvFile);
                    $uploadHandler->upload($firstResultatForUpload, 'pvImageFile');
                    // Récupérer le nom généré
                    $newFilename = $firstResultatForUpload->getPvImageName();
                }

                // 4. Persistence des résultats avec le nom de fichier
                foreach ($resultatsToProcess as $res) {
                    if ($newFilename) {
                        $res->setPvImageName($newFilename);
                    }
                    $em->persist($res);
                }

                $em->flush();
                $em->getConnection()->commit();

                $logsRepository->logAction(
                    "RESULTATS_ENREGISTRE",
                    $freshUser,
                    $request->getClientIp(),
                    $request->headers->get("User-Agent"),
                    [
                        "bureauDeVote" => $freshBureau->getNom(),
                        "assesseur" => $freshUser->getNom(),
                        "nombreVoix" => count($resultatsToProcess),
                        "pvImageName" => $newFilename
                    ]
                );


                $this->addFlash('success', 'Résultats enregistrés avec succès !');
                return $this->redirectToRoute('app_assesseur_dashboard');

            } catch (\Exception $e) {
                $em->getConnection()->rollBack();
                $this->addFlash('error', 'Erreur Transaction : ' . $e->getMessage());
                return $this->redirectToRoute('app_assesseur_saisie_resultats', ['id' => $id]);
            }
        }

        return $this->render('assesseur/saisie_globale.html.twig', [
            'form' => $form->createView(),
            'managedBureau' => $managedBureau,
            'existingPvName' => $existingPvName,
            'resultatWithPv' => $resultatWithPv
        ]);
    }



    #[Route('/observations', name: 'app_assesseur_observations', methods: ['GET', 'POST'])]
    public function observations(Request $request, EntityManagerInterface $em, LogsRepository $logsRepository, ObservationRepository $observationRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user)
            return $this->redirectToRoute('app_login');

        $assignedCentre = $user->getAssignedCentre();

        if (!$assignedCentre) {
            $this->addFlash('error', "Vous n'êtes assigné à aucun centre de vote.");
            return $this->redirectToRoute('app_home');
        }

        $observation = new Observation();
        $form = $this->createForm(ObservationType::class, $observation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $observation->setAssesseur($user);
            $observation->setCentreDeVote($assignedCentre);

            $em->persist($observation);
            $em->flush();

            $logDetails = [
                'observation' => $observation->getContenu() ?? "",
                'niveau' => $observation->getNiveau() ?? "",
                'centre' => $assignedCentre->getId()
            ];

            $logsRepository->logAction(
                "OBSERVATION_SIGNALEE",
                $user,
                ip: $request->getClientIp(),
                userAgent: $request->headers->get('User-Agent'),
                details: $logDetails
            );

            $this->addFlash('success', "Observation enregistrée.");
            return $this->redirectToRoute('app_assesseur_observations');
        }

        $historique = $observationRepository->findByCentre((string) $assignedCentre->getId());

        return $this->render('assesseur/observations.html.twig', [
            'form' => $form->createView(),
            'historique' => $historique,
            'bureau' => null,
            'centre' => $assignedCentre
        ]);
    }
}
