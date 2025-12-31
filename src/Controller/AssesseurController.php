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


        $managedBureau = $user->getAssignedBureau();


        $bureau = $managedBureau;


        if (!$bureau) {
            if ($this->isGranted('ROLE_ADMIN')) {
                $this->addFlash('warning', 'Vous êtes administrateur sans bureau assigné. Veuillez en assigner un ou utiliser le dashboard admin.');
                return $this->redirectToRoute('admin');
            }
            // Assesseur sans bureau
            throw $this->createAccessDeniedException('Aucun bureau de vote ne vous est assigné. Contactez l\'administrateur.');
        }

        // Calculs ...
        $isVoteComplet = false;
        $sommeVoix = $resultatRepository->getSommeVoixParBureau((string) $bureau->getId());

        if ($sommeVoix >= $bureau->getNombreInscrits()) {
            $isVoteComplet = true;
        }

        // Calcul Taux de Participation
        $latestParticipation = $participationRepository->findLatestByBureau($bureau->getId());

        $nombreVotantsParticipation = $latestParticipation ? $latestParticipation->getNombreVotants() : 0;

        $nombreVotants = max($nombreVotantsParticipation, $sommeVoix);

        $tauxParticipation = 0;

        if ($bureau->getNombreInscrits() > 0) {
            $tauxParticipation = ($nombreVotants / $bureau->getNombreInscrits()) * 100;
        }

        // Récupération des messages
        $messages = $broadcastMessageRepository->findActiveMessages();

        // --- Gestion de la plage horaire ---
        $elections = $electionRepository->findAll();
        $election = $elections[0] ?? null;
        $isSaisieOuverte = true;
        $heureFermetureStr = '16:00'; // Default

        if ($election) {
            $dateElection = $election->getDateElection();
            $heureFermeture = $election->getHeureFermeture();

            if ($heureFermeture) {
                $heureFermetureStr = $heureFermeture->format('H:i');
                // Créer le DateTime de fermeture
                $fermetureDateTime = \DateTime::createFromFormat(
                    'Y-m-d H:i:s',
                    $dateElection->format('Y-m-d') . ' ' . $heureFermeture->format('H:i:s'),
                    new \DateTimeZone('Africa/Porto-Novo')
                );

                // Ajouter 2 heures de tolérance (comme demandé: "2 à 3 heures")
                $limiteSaisie = (clone $fermetureDateTime)->modify('+2 hours');
                $now = new \DateTime('now', new \DateTimeZone('Africa/Porto-Novo'));

                if ($now > $limiteSaisie) {
                    $isSaisieOuverte = false;
                }
            }
        }

        // Resultats déjà saisis pour affichage/modif
        $mesResultats = $resultatRepository->findBy(['bureauDeVote' => $bureau]);

        return $this->render('assesseur/index.html.twig', [
            'bureau' => $bureau,
            'isVoteComplet' => $isVoteComplet,
            'sommeVoix' => $sommeVoix,
            'tauxParticipation' => floor($tauxParticipation * 100) / 100,
            'nombreVotants' => $nombreVotants,
            'messages' => $messages,
            'election' => $election,
            'isSaisieOuverte' => $isSaisieOuverte,
            'mesResultats' => $mesResultats
        ]);
    }

    #[Route('/participation', name: 'app_assesseur_participation', methods: ['GET', 'POST'])]
    public function participation(Request $request, EntityManagerInterface $em, GeoVerifier $geoVerifier, LoggerInterface $logger, LogsRepository $logsRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $bureau = $user->getAssignedBureau();

        if (!$bureau) {
            $this->addFlash('error', 'Vous devez être assigné à un bureau de vote pour effectuer un pointage.');
            return $this->redirectToRoute('app_assesseur_dashboard');
        }

        // Reload the bureau from database to ensure it's managed by Doctrine
        $managedBureau = $em->find(BureauDeVote::class, $bureau->getId());

        if (!$managedBureau) {
            throw new \Exception('Bureau de vote introuvable');
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

                // Vérification Géolocalisation via les champs cachés remplis en JS
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

                // efface les entités du cache
                $em->clear();

                // recharge les entités pour s'assurer qu'elles sont bien gérées
                $freshBureau = $em->find(BureauDeVote::class, $managedBureau->getId());
                $freshUser = $em->find(User::class, $user->getId());

                // Créer une nouvelle participation avec les entités gérées
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
            'bureau' => $bureau,
        ]);
    }

    /**
     * Pointage des résultats
     */
    #[Route('/saisie-resultats', name: 'app_assesseur_saisie_resultats', methods: ['GET', 'POST'])]
    public function saisieGlobale(
        Request $request,
        EntityManagerInterface $em,
        ResultatRepository $resultatRepository,
        PartiRepository $partiRepository,
        BureauDeVoteRepository $bureauDeVoteRepository,
        ElectionRepository $electionRepository,
        UploadHandler $uploadHandler,
        LogsRepository $logsRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user)
            return $this->redirectToRoute('app_login');

        // Vérification Affectation
        $assignedBureau = $user->getAssignedBureau();
        if (!$assignedBureau) {
            $this->addFlash('warning', 'Vous n\'êtes affecté à aucun bureau de vote.');
            return $this->redirectToRoute('app_assesseur_dashboard');
        }

        // RECHARGEMENT DE L'ENTITÉ POUR ÉVITER L'ERREUR "DETACHED ENTITY"
        $managedBureau = $bureauDeVoteRepository->find($assignedBureau->getId());
        if (!$managedBureau) {
            throw $this->createNotFoundException('Bureau introuvable.');
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

        foreach ($resultatsExistants as $res) {
            $formData['parti_' . $res->getParti()->getId()] = $res->getNombreVoix();
            if ($res->getPvImageName()) {
                $existingPvName = $res->getPvImageName();
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
                    'existingPvName' => $existingPvName
                ]);
            }

            // Gestion Upload
            $pvFile = $form->get('pvImageFile')->getData();
            $newFilename = $existingPvName;

            // Vérification si aucun fichier n'existe et aucun n'est envoyé
            if (!$pvFile && !$existingPvName) {
                $this->addFlash('error', 'Vous devez uploader une photo du PV.');
                return $this->redirectToRoute('app_assesseur_saisie_resultats');
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
                return $this->redirectToRoute('app_assesseur_saisie_resultats');
            }
        }

        return $this->render('assesseur/saisie_globale.html.twig', [
            'form' => $form->createView(),
            'managedBureau' => $managedBureau,
            'existingPvName' => $existingPvName
        ]);
    }



    #[Route('/observations', name: 'app_assesseur_observations', methods: ['GET', 'POST'])]
    public function observations(Request $request, EntityManagerInterface $em, LogsRepository $logsRepository, ObservationRepository $observationRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user)
            return $this->redirectToRoute('app_login');

        $managedBureau = $user->getAssignedBureau();
        if (!$managedBureau) {
            $this->addFlash('error', "Vous n'êtes assigné à aucun bureau de vote.");
            return $this->redirectToRoute('app_home');
        }

        $observation = new Observation();
        $form = $this->createForm(ObservationType::class, $observation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $observation->setAssesseur($user);
            $observation->setBureauDeVote($managedBureau);

            $em->persist($observation);
            $em->flush();

            $logsRepository->logAction(
                "OSERVATION_SIGNALEE",
                $user,
                ip: $request->getClientIp(),
                userAgent: $request->headers->get('User-Agent'),
                details: [
                    'bureau' => $managedBureau->getId(),
                    'observation' => $observation->getContenu() ?? "",
                    'niveau' => $observation->getNiveau() ?? "",
                ]
            );

            $this->addFlash('success', "Observation enregistrée.");
            return $this->redirectToRoute('app_assesseur_observations');
        }

        $historique = $observationRepository->findByBureau((string) $managedBureau->getId() ?? '');

        return $this->render('assesseur/observations.html.twig', [
            'form' => $form->createView(),
            'historique' => $historique,
            'bureau' => $managedBureau
        ]);
    }
}
