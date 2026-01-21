<?php

namespace App\Controller;

use App\Entity\BureauDeVote;
use App\Entity\CentreDeVote;
use App\Entity\Observation;
use App\Entity\Participation;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use App\Entity\Resultat;
use App\Entity\SignalementInscrits;
use App\Entity\User;
use App\Form\ObservationType;
use App\Form\ParticipationType;
use App\Form\SignalementInscritsType;
use App\Repository\BroadcastMessageRepository;
use App\Repository\BureauDeVoteRepository;
use App\Repository\ElectionRepository;
use App\Repository\LogsRepository;
use App\Repository\ObservationRepository;
use App\Repository\PartiRepository;
use App\Repository\ParticipationRepository;
use App\Repository\ResultatRepository;
use App\Service\GeoVerifier;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use App\Form\GlobalResultatsType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Vich\UploaderBundle\Handler\UploadHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

#[Route('/assesseur')]
class AssesseurController extends AbstractController
{
    #[Route('/', name: 'app_assesseur_dashboard')]
    public function index(
        ResultatRepository $resultatRepository,
        ParticipationRepository $participationRepository,
        BroadcastMessageRepository $broadcastMessageRepository,
        ElectionRepository $electionRepository,
        EntityManagerInterface $em
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $assignedCentre = $user->getAssignedCentre();

        $bureaux = [];
        $isSupervisor = false;

        // Vérification de la validation des Inscrits

        // 1. Vérifier si on exige la validation. En Dev/Test, on peut être souple sauf si env="prod"
        $isProd = $this->getParameter('kernel.environment') === 'prod';

        if ($assignedCentre) {
            // Vérifier le rôle si nécessaire, ou supposer que le centre assigné donne accès à ses bureaux
            $bureaux = $assignedCentre->getBureaux()->toArray();

            // TRI ALPHABÉTIQUE / CROISSANT (PV 01, PV 02...)
            usort($bureaux, function ($a, $b) {
                return strnatcmp($a->getCode(), $b->getCode());
            });

            // Si on veut garder la distinction visuelle "Superviseur"
            if ($this->isGranted('ROLE_SUPERVISEUR')) {
                $isSupervisor = true;
            }

            // Vérifier si un SignalementInscrits validé existe pour ce centre
            // Nous recherchons un signalement avec le statut VALIDATED.
            $repoSignalement = $em->getRepository(SignalementInscrits::class);
            $validatedExists = $repoSignalement->findOneBy([
                'centreDeVote' => $assignedCentre,
                'statut' => 'VALIDATED'
            ]);

            if ($validatedExists) {
                $isInscritsValidated = true;
            }
        } else {
            if ($this->isGranted('ROLE_ADMIN')) {
                $this->addFlash('warning', 'Vous êtes administrateur sans centre assigné. Veuillez en assigner un ou utiliser le dashboard admin.');
                return $this->redirectToRoute('admin');
            }
            throw $this->createAccessDeniedException('Aucun centre de vote ne vous est assigné. Contactez l\'administrateur.');
        }

        // 2. VÉRIFIER SI TOUS LES BUREAUX ONT DES INSCRITS
        // Même si c'est validé, si un nouveau bureau est ajouté et a 0 inscrit, on doit relancer l'alerte
        $missingInscrits = false;
        foreach ($bureaux as $b) {
            if ($b->getNombreInscrits() <= 0) {
                $missingInscrits = true;
                break;
            }
        }

        if ($missingInscrits) {
            $isInscritsValidated = false; // Forcer la re-validation/alerte
        }


        // Verrouillage logique
        // Si nous sommes en PROD, nous EXIGEONS la validation pour permettre la saisie.
        $isLocked = $isProd && !$isInscritsValidated;


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
            $limiteSaisie = (clone $fermetureDateTime)->modify('+2 hours'); // Tolérance élargie (2h)
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
                'detailsResultats' => $resultats,
                'hasInscritsDefined' => $bureau->getNombreInscrits() > 0 // Info pour le template
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
            'isLocked' => $isLocked,
            'isInscritsValidated' => $isInscritsValidated,
            // Pour compatibilité template
            'singleBureauStat' => null
        ]);
    }

    #[Route('/guide', name: 'app_assesseur_guide')]
    public function guide(): Response
    {
        return $this->render('assesseur/guide.html.twig');
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

        // Vérification d'accès : Le bureau doit appartenir au centre assigné à l'utilisateur
        if (!$assignedCentre || $managedBureau->getCentre()->getId() !== $assignedCentre->getId()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce bureau.');
        }

        $participation = new Participation();
        // Définir le bureau et l'assesseur AVANT la gestion du formulaire pour que le validateur puisse y accéder
        $participation->setBureauDeVote($managedBureau);
        $participation->setAssesseur($user);

        $logger->info('Bureau de vote: ' . $managedBureau->getNom());
        $logger->info('Assesseur: ' . $user->getNom() . ' (' . $user->getEmail() . ')');

        // VERROUILLAGE PROD : Validation des Inscrits
        if ($this->getParameter('kernel.environment') === 'prod') {
            $signalement = $em->getRepository(SignalementInscrits::class)->findOneBy([
                'centreDeVote' => $managedBureau->getCentre(),
                'statut' => 'VALIDATED'
            ]);
            if (!$signalement) {
                $this->addFlash('error', '🔒 Verrouillé : Vous devez déclarer les inscrits et attendre la validation avant de continuer.');
                return $this->redirectToRoute('app_assesseur_declarer_inscrits');
            }
        }


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
                $participation->setHeurePointage(new DateTimeImmutable());

                // Debug: vérifier si bureau et assesseur sont bien remplis
                if (!$participation->getBureauDeVote()) {
                    throw new \Exception('Le bureau de vote est null après le traitement du formulaire !');
                }
                if (!$participation->getAssesseur()) {
                    throw new \Exception('L\'assesseur est null après le traitement du formulaire !');
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
                        'isNear' => $isNear,
                        'centerCoords' => [
                            'lat' => $centre->getLatitude(),
                            'lon' => $centre->getLongitude()
                        ],
                        'distanceFromAssesseur' => number_format($geoVerifier->calculateDistance(
                            (float) $lat,
                            (float) $lon,
                            $centre->getLatitude(),
                            $centre->getLongitude()
                        ) / 1000, 2, ",", " ") . " km"
                    ]);
                }

                $logger->info((string) $participation);


                // On efface pour nettoyer l'unité de travail
                $em->clear();

                // On recharge les entités "fraîches"
                $freshBureau = $em->find(BureauDeVote::class, $managedBureau->getId());
                $freshUser = $em->find(User::class, $user->getId());

                if (!$freshBureau || !$freshUser) {
                    // très peu de chance de tomber ici
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
                $heure = $freshParticipation->getHeurePointage()->format("d M Y H:i");

                $logsRepository->logAction(
                    "PARTICIPATION_ENREGISTRE",
                    $freshUser,
                    $request->getClientIp(),
                    $request->headers->get("User-Agent"),
                    [
                        "centreDeVote" => $freshBureau->getNom() . "(" . $freshBureau->getCentre()->getArrondissement() . "- " . $freshBureau->getCentre()->getCommune() . ")",
                        "assesseur" => ucfirst($freshUser->getPrenom()) . " " . ucfirst(substr($freshUser->getNom(), 0, 1)) . ".",
                        "nombreVotants" => $participation->getNombreVotants(),
                        "heurePointage" => $heure,
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
        UploaderHelper $uploaderHelper,
        string $id
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user)
            return $this->redirectToRoute('app_login');

        // Résolution du Bureau
        $assignedCentre = $user->getAssignedCentre();

        $managedBureau = $bureauDeVoteRepository->find($id);
        if (!$managedBureau) {
            throw $this->createNotFoundException('Bureau introuvable.');
        }

        // Vérification d'Accès
        if (!$assignedCentre || $managedBureau->getCentre()->getId() !== $assignedCentre->getId()) {
            throw $this->createAccessDeniedException('Accès refusé pour ce bureau.');
        }

        $isModification = false;
        // vérification si c'est une modification 
        $resultatsExistants = $resultatRepository->findBy(['bureauDeVote' => $managedBureau]);
        if ($resultatsExistants) {
            $isModification = true;
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

        // SURCHARGE DEV
        if ($this->getParameter('kernel.environment') === 'dev') {
            $isSaisieOuverte = true;
        }

        if (!$isSaisieOuverte) {
            $this->addFlash('error', 'La saisie des résultats est fermée.');
            return $this->redirectToRoute('app_assesseur_dashboard');
        }

        // VERROUILLAGE PROD : Validation des Inscrits
        if ($this->getParameter('kernel.environment') === 'prod') {
            $signalement = $em->getRepository(SignalementInscrits::class)->findOneBy([
                'centreDeVote' => $managedBureau->getCentre(),
                'statut' => 'VALIDATED'
            ]);
            if (!$signalement) {
                $this->addFlash('error', '🔒 Verrouillé : Vous devez déclarer les inscrits et attendre la validation avant de continuer.');
                return $this->redirectToRoute('app_assesseur_declarer_inscrits');
            }
        }

        // Chargement Données
        $partis = $partiRepository->findAll();
        $resultatsExistants = $resultatRepository->findBy(['bureauDeVote' => $managedBureau]);

        // Préparation des données du formulaire
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
            $form->get('pvImageFile')->getConfig()->getOptions()['required'] = false; // Note: Les contraintes restent mais l'attribut 'required' aide le frontend
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // 1. SÉCURITÉ : Vérification de la géolocalisation
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
                        [
                            'centreDeVote' => $managedBureau->getNom() . ' (' . $managedBureau->getCentre()->getArrondissement() . ' - ' . $managedBureau->getCentre()->getCommune() . ')',
                            'isNear' => $isNear,
                            'distance' => number_format($geoVerifier->calculateDistance(
                                (float) $lat,
                                (float) $lon,
                                $centre->getLatitude(),
                                $centre->getLongitude()
                            ) / 1000, 2, ",", " ") . "km"
                        ]
                    );

                    $this->addFlash('error', '⛔ Position non valide : Vous êtes trop loin du centre de vote pour saisir les résultats.');
                    return $this->redirectToRoute('app_assesseur_saisie_resultats', ['id' => $id]);
                }
            }

            // Validation : Total vs Inscrits
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
                    $resultat->setUpdatedAt(new DateTimeImmutable());

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

                $pvImage = $uploaderHelper->asset($firstResultatForUpload, 'pvImageFile');

                $logsRepository->logAction(
                    $isModification ? "RESULTATS_MODIFIE" : "RESULTATS_ENREGISTRE",
                    $freshUser,
                    $request->getClientIp(),
                    $request->headers->get("User-Agent"),
                    [
                        "centreDeVote" => $freshBureau->getNom() . ' (' . $freshBureau->getCentre()->getArrondissement() . ' - ' . $freshBureau->getCentre()->getCommune() . ')',
                        "assesseur" => ucfirst($freshUser->getPrenom()) . " " . ucfirst(substr($freshUser->getNom(), 0, 1)) . ".",
                        "isNear" => $isNear,
                        "distance" => number_format($geoVerifier->calculateDistance(
                            (float) $lat,
                            (float) $lon,
                            $freshBureau->getCentre()->getLatitude(),
                            $freshBureau->getCentre()->getLongitude()
                        ) / 1000, 2, ",", " ") . "km",
                        "pvImageName" => $pvImage
                    ]
                );


                $this->addFlash('success', $isModification ? 'Résultats modifiés avec succès !' : 'Résultats enregistrés avec succès !');
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
    public function observations(
        Request $request,
        EntityManagerInterface $em,
        LogsRepository $logsRepository,
        ObservationRepository $observationRepository,
        #[Autowire('%env(AZURE_PUBLIC_URL)%')] string $azurePublicUrl,
        #[Autowire('%env(AZURE_STORAGE_CONTAINER_IMAGES)%')] string $azureContainer
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user)
            return $this->redirectToRoute('app_login');

        $assignedCentre = $user->getAssignedCentre();

        if (!$assignedCentre) {
            $this->addFlash('error', "Vous n'êtes assigné à aucun centre de vote.");
            return $this->redirectToRoute('app_home');
        }

        // on recharge l'entité pour éviter les Detached/Proxy issues
        $assignedCentre = $em->getRepository(CentreDeVote::class)->find($assignedCentre->getId());
        if (!$assignedCentre) {
            throw $this->createNotFoundException("Centre de vote introuvable.");
        }

        $observation = new Observation();
        $form = $this->createForm(ObservationType::class, $observation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $observation->setAssesseur($user);
            $observation->setCentreDeVote($assignedCentre);

            $em->persist($observation);
            $em->flush();

            $hasImage = $observation->getImageName();
            // on capture les données pour les logs
            $logDetails = [
                'centre' => $assignedCentre->getNom() . ' (' . $assignedCentre->getArrondissement() . ' - ' . $assignedCentre->getCommune() . ')',
                'observation' => $observation->getContenu() ?? "",
                'niveau' => $observation->getNiveau() ?? "",
                'hasImage' => $hasImage ? 'Oui' : 'Non',
                'imageFile' => $hasImage ? $azurePublicUrl . '/' . $azureContainer . '/' . $hasImage : ""
            ];

            // on vide le manager pour éviter les ghost objects
            $userId = $user->getId();
            $clientIp = $request->getClientIp();
            $userAgent = $request->headers->get('User-Agent');

            $em->clear();

            // Recharger la référence utilisateur pour le log
            $logUser = $em->getReference(User::class, $userId);

            $logsRepository->logAction(
                "OBSERVATION_SIGNALEE",
                $logUser,
                ip: $clientIp,
                userAgent: $userAgent,
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
    #[Route('/declarer-inscrits', name: 'app_assesseur_declarer_inscrits', methods: ['GET', 'POST'])]
    public function declarerInscrits(
        Request $request,
        EntityManagerInterface $em,
        LogsRepository $logsRepository,
        MailerInterface $mailer,
        LoggerInterface $logger
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $assignedCentre = $user->getAssignedCentre();

        if (!$assignedCentre) {
            $this->addFlash('error', "Vous n'êtes assigné à aucun centre de vote.");
            return $this->redirectToRoute('app_assesseur_dashboard');
        }

        // on vérifie si le signalement existe déjà
        $repo = $em->getRepository(SignalementInscrits::class);
        $signalement = $repo->findOneBy(['assesseur' => $user, 'centreDeVote' => $assignedCentre], ['createdAt' => 'DESC']);

        if (!$signalement) { // si le signalement n'existe pas, on en crée un nouveau
            $signalement = new SignalementInscrits();
            $signalement->setAssesseur($user);
            $signalement->setCentreDeVote($assignedCentre);
            $signalement->setAuteurNom($user->getPrenom() . ' ' . $user->getNom());
        }

        // Préparer les données existantes pour le champ JSON
        $initialJson = null;
        if (!empty($signalement->getRepartitionBureaux())) {
            $initialJson = json_encode($signalement->getRepartitionBureaux());
        }

        $form = $this->createForm(SignalementInscritsType::class, $signalement);

        // Pré-remplissage manuel du champ non mappé
        if ($initialJson) {
            $form->get('repartitionBureauxJson')->setData($initialJson);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // on force le centre et l'assesseur
            $signalement->setCentreDeVote($assignedCentre);
            $signalement->setAssesseur($user);
            // on met le statut en PENDING, on validera le signalement plus tard en admin
            $signalement->setStatut('PENDING');

            // on traite le json
            $jsonBureaux = $form->get('repartitionBureauxJson')->getData();
            if ($jsonBureaux) {
                $data = json_decode($jsonBureaux, true);
                if (is_array($data)) {
                    $signalement->setRepartitionBureaux($data);
                }
            }

            // on recalcule le total si 0
            if ($signalement->getNombreInscritsTotal() == 0 && !empty($signalement->getRepartitionBureaux())) {
                $total = 0;
                foreach ($signalement->getRepartitionBureaux() as $b) {
                    $total += (int) ($b['inscrits'] ?? 0);
                }
                $signalement->setNombreInscritsTotal($total);
            }

            $em->persist($signalement);
            $em->flush();

            // on log le signalement
            $logsRepository->logAction(
                "SIGNALEMENT_INSCRITS",
                $user,
                $request->getClientIp(),
                $request->headers->get("User-Agent"),
                [
                    "centre" => $assignedCentre->getNom(),
                    "total" => $signalement->getNombreInscritsTotal()
                ]
            );

            // EMAIL NOTIFICATION TO ADMIN
            $adminEmail = $_ENV['ADMIN_EMAIL'] ?? 'admin@surevote.bj';
            $fromEmail = $_ENV['MAILER_FROM'] ?? 'admin@surevote.bj';
            $email = (new TemplatedEmail())
                ->from(new Address($fromEmail, 'SureVote Notification'))
                ->to($adminEmail)
                ->subject('Nouvelle déclaration d\'inscrits - ' . $assignedCentre->getNom())
                ->htmlTemplate('emails/admin_signalement_notification.html.twig')
                ->context([
                    'signalement' => $signalement
                ]);

            try {
                $mailer->send($email);
            } catch (\Exception $e) {
                $logger->error('Failed to send email: ' . $e->getMessage());
            }

            $this->addFlash('success', 'Déclaration du nombre d\'inscrits enregistrée.');
            return $this->redirectToRoute('app_assesseur_dashboard');
        }

        return $this->render('assesseur/declarer_inscrits.html.twig', [
            'form' => $form->createView(),
            'centre' => $assignedCentre,
            'existingSignalement' => $signalement
        ]);
    }

    #[Route('/configuration', name: 'app_assesseur_config', methods: ['GET', 'POST'])]
    public function updateConfig(Request $request, EntityManagerInterface $em, LogsRepository $logsRepository, MailerInterface $mailer, LoggerInterface $logger): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $assignedCentre = $user->getAssignedCentre();
        if (!$assignedCentre) {
            $this->addFlash('error', "Vous n'êtes assigné à aucun centre de vote.");
            return $this->redirectToRoute('app_assesseur_dashboard');
        }

        // dd($_ENV);

        // Formulaire simple pour le nombre de bureaux
        $defaultCount = $assignedCentre->getNombreBureauxReels() ?? $assignedCentre->getBureaux()->count();

        $form = $this->createFormBuilder(['nombreBureaux' => $defaultCount])
            ->add('nombreBureaux', IntegerType::class, [
                'label' => 'Nombre de bureaux de vote ouverts',
                'attr' => ['class' => 'form-input w-full rounded-xl border-slate-200'],
                'constraints' => [
                    new Positive(),
                    new NotBlank()
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Valider la configuration',
                'attr' => ['class' => 'btn btn-primary w-full mt-4 bg-benin-green text-white py-3 rounded-xl font-bold hover:bg-benin-green/90 transition-all']
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $newCount = $data['nombreBureaux'];
            $currentSystemCount = $assignedCentre->getBureaux()->count();

            $assignedCentre->setNombreBureauxReels($newCount);

            // Si le nombre correspond à ce qu'on a déjà, c'est validé
            if ($newCount === $currentSystemCount) {
                $assignedCentre->setIsConfigValidated(true);
                $this->addFlash('success', 'Configuration validée. Le nombre de bureaux est correct.');
            } else {
                // Sinon, c'est une demande de modification
                $assignedCentre->setIsConfigValidated(false);
                $this->addFlash('warning', 'Différence détectée. Une demande de validation a été envoyée à l\'administrateur.');

                // Notification
                // 1. Get Supervisors of the same Departement
                $userRepo = $em->getRepository(User::class);
                $supervisors = [];
                if ($assignedCentre->getDepartement()) {
                    $supervisors = $userRepo->findSupervisorsByDepartement($assignedCentre->getDepartement());
                }

                // 2. Get Admins
                $admins = $userRepo->findAdmins();

                // 3. Build Recipient Lists
                $toAddresses = [];
                foreach ($supervisors as $supervisor) {
                    if ($supervisor->getEmail()) {
                        $toAddresses[] = new Address($supervisor->getEmail(), $supervisor->getFullName());
                    }
                }

                // Fallback: If no supervisors, send to Admin
                if (empty($toAddresses)) {
                    $toAddresses[] = new Address($_ENV['ADMIN_EMAIL'] ?? 'admin@surevote.bj');
                }

                $ccAddresses = [];
                foreach ($admins as $admin) {
                    if ($admin->getEmail()) {
                        $ccAddresses[] = new Address($admin->getEmail(), $admin->getFullName());
                    }
                }
                // Add Generic Admin Email to CC if not already there
                $genericAdmin = $_ENV['ADMIN_EMAIL'] ?? 'admin@surevote.bj';
                if (!in_array($genericAdmin, array_map(fn($a) => $a->getAddress(), $ccAddresses)) && !in_array($genericAdmin, array_map(fn($a) => $a->getAddress(), $toAddresses))) {
                    $ccAddresses[] = new Address($genericAdmin);
                }

                $fromEmail = $_ENV['MAILER_FROM'] ?? 'admin@surevote.bj';
                $siteURL = $_ENV['SITE_ADMIN_URL'] ?? 'https://surevote.bj';

                $email = (new TemplatedEmail())
                    ->from(new Address($fromEmail, 'SureVote Notification'))
                    ->to(...$toAddresses)
                    ->cc(...$ccAddresses)
                    ->subject('⚠️ Anomalie Configuration - ' . $assignedCentre->getNom())
                    ->htmlTemplate('emails/admin_alert.html.twig')
                    ->context([
                        'title' => 'Anomalie de Configuration Detectée',
                        'message' => sprintf(
                            "Le centre %s (%s - %s) signale %d postes de vote (PV), mais le système en compte %d.\n\nUtilisateur: %s",
                            $assignedCentre->getNom(),
                            $assignedCentre->getArrondissement(),
                            $assignedCentre->getCommune(),
                            $newCount,
                            $currentSystemCount,
                            $user->getFullName()
                        ),
                        'actionUrl' => $siteURL,
                        'centre' => $assignedCentre
                    ]);

                // Fallback text
                $email->text(sprintf(
                    "ALERT: Anomalie de configuration au centre %s.\nSignalé: %d PVs | Système: %d PVs.\nVeuillez vérifier.",
                    $assignedCentre->getNom(),
                    $newCount,
                    $currentSystemCount
                ));

                try {
                    $mailer->send($email);
                } catch (\Exception $e) {
                    // Log error but don't stop flow
                    $logger->error('Failed to send email: ' . $e->getMessage());
                }
            }

            $em->flush();

            return $this->redirectToRoute('app_assesseur_dashboard');
        }

        return $this->render('assesseur/config_centre.html.twig', [
            'form' => $form->createView(),
            'centre' => $assignedCentre,
            'currentSystemCount' => $assignedCentre->getBureaux()->count()
        ]);
    }
}
