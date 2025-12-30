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
            'tauxParticipation' => round($tauxParticipation, 2),
            'nombreVotants' => $nombreVotants,
            'messages' => $messages,
            'election' => $election,
            'isSaisieOuverte' => $isSaisieOuverte,
            'mesResultats' => $mesResultats
        ]);
    }

    #[Route('/participation', name: 'app_assesseur_participation', methods: ['GET', 'POST'])]
    public function participation(Request $request, EntityManagerInterface $em, GeoVerifier $geoVerifier, LoggerInterface $logger): Response
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
        UploadHandler $uploadHandler
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

            // dd($totalVoixSaisies);

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

            // 1. Gestion Upload Unique
            $pvFile = $form->get('pvImageFile')->getData();
            $newFilename = $existingPvName;

            if ($pvFile instanceof UploadedFile) {
                try {
                    // Utilisation de VichUploader pour gérer l'upload et le nommage
                    $tempResultat = new Resultat();
                    $tempResultat->setPvImageFile($pvFile);
                    $uploadHandler->upload($tempResultat, 'pvImageFile');

                    $newFilename = $tempResultat->getPvImageName();
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload du PV: ' . $e->getMessage());
                    return $this->redirectToRoute('app_assesseur_saisie_resultats');
                }
            }

            if (!$newFilename) {
                // Si aucun fichier n'a été uploadé (ni avant ni maintenant)
                if (!$existingPvName) {
                    $this->addFlash('error', 'Vous devez uploader une photo du PV.');
                    return $this->redirectToRoute('app_assesseur_saisie_resultats');
                }
            }

            // Reload user specifically for persistence context
            $managedUser = $em->find(User::class, $user->getId());

            // 2. Traitement des Votes
            foreach ($partis as $parti) {
                $fieldName = 'parti_' . $parti->getId();
                $voix = $form->get($fieldName)->getData();

                // Chercher existant ou Créer
                $resultat = null;
                foreach ($resultatsExistants as $existing) {
                    if ($existing->getParti()->getId() === $parti->getId()) {
                        $resultat = $existing;
                        break;
                    }
                }

                if (!$resultat) {
                    $resultat = new Resultat();
                    $resultat->setBureauDeVote($managedBureau);
                    $resultat->setParti($parti);
                    $em->persist($resultat);
                }

                $resultat->setAssesseur($managedUser);
                $resultat->setNombreVoix((int) $voix);
                $resultat->setPvImageName($newFilename);
                $resultat->setUpdatedAt(new \DateTimeImmutable());
                $em->persist($resultat);
            }

            $em->flush();

            $this->addFlash('success', 'Résultats enregistrés avec succès !');
            return $this->redirectToRoute('app_assesseur_dashboard');
        }

        return $this->render('assesseur/saisie_globale.html.twig', [
            'form' => $form->createView(),
            'managedBureau' => $managedBureau,
            'existingPvName' => $existingPvName
        ]);
    }

    #[Route('/resultat/{id}', name: 'app_assesseur_resultat', defaults: ['id' => null], methods: ['GET', 'POST'])]
    public function resultat(
        Request $request,
        EntityManagerInterface $em,
        ResultatRepository $resultatRepository,
        GeoVerifier $geoVerifier,
        LogsRepository $logsRepository,
        ElectionRepository $electionRepository,
        ?string $id = null,
        LoggerInterface $loggerInterface
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $bureau = $user->getAssignedBureau();

        if (!$bureau) {
            $loggerInterface->warning('erreur 1');
            $this->addFlash('error', 'Vous devez être assigné à un bureau de vote pour saisir un résultat.');
            return $this->redirectToRoute('app_assesseur_dashboard');
        }

        // --- Check Time Window ---
        $elections = $electionRepository->findAll();
        $election = $elections[0] ?? null;

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
                $loggerInterface->warning("Tentative de saisie hors délai", ["date_election" => $dateElection->format("Y-m-d"), "now" => $now, 'limit' => $limiteSaisie]);
                $this->addFlash('error', 'La période de saisie des résultats est fermée (Délai dépassé).');
                return $this->redirectToRoute('app_assesseur_dashboard');
            }
        }
        // -------------------------

        // Reload the bureau from database to ensure it's managed by Doctrine
        $managedBureau = $em->find(BureauDeVote::class, $bureau->getId());

        if (!$managedBureau) {
            throw new \Exception('Bureau de vote introuvable');
        }

        $modeEdition = false;
        $anciensVoix = 0;

        if ($id) {
            $resultat = $resultatRepository->find($id);
            if (!$resultat || $resultat->getBureauDeVote()->getId() !== $managedBureau->getId()) {
                $this->addFlash('error', 'Résultat introuvable ou accès refusé.');
                return $this->redirectToRoute('app_assesseur_dashboard');
            }
            $modeEdition = true;
            $anciensVoix = $resultat->getNombreVoix();
        } else {
            $resultat = new Resultat();
            $resultat->setBureauDeVote($managedBureau);
            $resultat->setAssesseur($user);
        }

        $form = $this->createForm(ResultatsType::class, $resultat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 1. Validation de base (Soft Check)
            if ($resultat->getNombreVoix() > $managedBureau->getNombreInscrits()) {
                $form->get('nombreVoix')->addError(new FormError(
                    sprintf(
                        'Le nombre de voix (%d) dépasse le nombre d\'inscrits (%d).',
                        $resultat->getNombreVoix(),
                        $managedBureau->getNombreInscrits()
                    )
                ));
            }

            // 2. Validation de l'unicité parti/bureau (Soft Check)
            $existingResultat = $resultatRepository->findOneBy([
                'bureauDeVote' => $managedBureau,
                'parti' => $resultat->getParti()
            ]);

            if ($existingResultat) {
                if (!$modeEdition || ($existingResultat->getId() !== $resultat->getId())) {
                    $form->get('parti')->addError(new FormError(
                        sprintf('Un résultat pour le parti "%s" a déjà été saisi pour ce bureau.', $resultat->getParti()->getNom())
                    ));
                }
            }

            // 3. Validation de la somme totale (Soft Check)
            if (count($form->getErrors(true)) === 0) {
                $sommeActuelle = $resultatRepository->getSommeVoixParBureau((string) $managedBureau->getId());

                if ($modeEdition) {
                    $sommeActuelle -= $anciensVoix;
                }

                $nouveauTotal = $sommeActuelle + $resultat->getNombreVoix();

                if ($nouveauTotal > $managedBureau->getNombreInscrits()) {
                    $form->get('nombreVoix')->addError(new FormError(
                        sprintf(
                            'Impossible ! La somme totale des voix (%d) dépasserait le nombre d\'inscrits (%d). Total actuel: %d.',
                            $nouveauTotal,
                            $managedBureau->getNombreInscrits(),
                            $sommeActuelle
                        )
                    ));
                }
            }

            if ($form->isValid()) {
                // Geo check
                $lat = $request->request->get('lat');
                $lon = $request->request->get('lon');
                $ipAddress = $request->getClientIp();
                $userClient = $request->headers->get("User-Agent");

                // --- PREPARATION DONNEES AVANT CLEAR ---
                $newDataVoix = $resultat->getNombreVoix();
                $newDataPartiId = $resultat->getParti()->getId();
                $newDataFile = $resultat->getPvImageFile();

                // --- DÉBUT TRANSACTION ---
                $em->getConnection()->beginTransaction();
                try {
                    $loggerInterface->info("Début Transaction Resultat", ['mode' => $modeEdition ? 'EDIT' : 'CREATE', 'id' => $id]);

                    // 1. VERROUILLAGE PESSIMISTE
                    $bureauId = $managedBureau->getId();
                    $lockedBureau = $em->find(BureauDeVote::class, $bureauId, LockMode::PESSIMISTIC_WRITE);

                    if (!$lockedBureau) {
                        throw new \Exception("Impossible de verrouiller le bureau de vote.");
                    }

                    // 2. CLEAN
                    $em->clear();

                    // 3. RECHARGEMENT DES ENTITÉS
                    $freshBureau = $em->find(BureauDeVote::class, $bureauId);
                    $freshUser = $em->find(User::class, $user->getId());
                    $freshParti = $em->find(Parti::class, $newDataPartiId);

                    if (!$freshBureau || !$freshUser || !$freshParti) {
                        throw new \Exception("Erreur rechargement entités (bureau/user/parti).");
                    }

                    // 4. LOGIQUE CREATE OU UPDATE
                    if ($modeEdition && $id) {
                        // --- UPDATE ---
                        $freshResultat = $em->find(Resultat::class, $id);
                        if (!$freshResultat) {
                            throw new \Exception("Résultat introuvable après reload (ID: $id).");
                        }

                        // Check Unicité (si parti changé)
                        if ($freshResultat->getParti()->getId() !== $freshParti->getId()) {
                            $check = $resultatRepository->findOneBy(['bureauDeVote' => $freshBureau, 'parti' => $freshParti]);
                            if ($check)
                                throw new \Exception("Doublon parti détecté (changement de parti vers un existant).");
                        }

                        // Check Somme
                        $sommeDb = $resultatRepository->getSommeVoixParBureau((string) $bureauId);
                        $oldVoix = $freshResultat->getNombreVoix();
                        // La somme en base inclut l'ancienne valeur, donc on la retire pour vérifier la nouvelle
                        $sommeSansMoi = $sommeDb - $oldVoix;

                        if (($sommeSansMoi + $newDataVoix) > $freshBureau->getNombreInscrits()) {
                            throw new \Exception(sprintf("Quota dépassé (%d > %d)", ($sommeSansMoi + $newDataVoix), $freshBureau->getNombreInscrits()));
                        }

                        // Update
                        $freshResultat->setNombreVoix($newDataVoix);
                        $freshResultat->setParti($freshParti);
                        $freshResultat->setAssesseur($freshUser);
                        if ($newDataFile) {
                            $freshResultat->setPvImageFile($newDataFile);
                        }
                        $freshResultat->setUpdatedAt(new \DateTimeImmutable());

                    } else {
                        // --- CREATE ---
                        $check = $resultatRepository->findOneBy(['bureauDeVote' => $freshBureau, 'parti' => $freshParti]);
                        if ($check)
                            throw new \Exception("Doublon parti détecté.");

                        $sommeDb = $resultatRepository->getSommeVoixParBureau((string) $bureauId);
                        if (($sommeDb + $newDataVoix) > $freshBureau->getNombreInscrits()) {
                            throw new \Exception(sprintf("Quota dépassé (%d > %d)", ($sommeDb + $newDataVoix), $freshBureau->getNombreInscrits()));
                        }

                        $freshResultat = new Resultat();
                        $freshResultat->setBureauDeVote($freshBureau);
                        $freshResultat->setAssesseur($freshUser);
                        $freshResultat->setParti($freshParti);
                        $freshResultat->setNombreVoix($newDataVoix);
                        if ($newDataFile) {
                            $freshResultat->setPvImageFile($newDataFile);
                        }
                        $em->persist($freshResultat);
                    }

                    // 5. COMMIT
                    $em->flush();
                    $em->getConnection()->commit();
                    $loggerInterface->info("Transaction OK");

                    // 6. LOGS
                    $logsRepository->logAction(
                        $modeEdition ? "MODIFICATION_PV" : "AJOUT_PV",
                        $freshUser,
                        $ipAddress,
                        $userClient,
                        [
                            'parti' => $freshParti->getNom(),
                            'nombreAncienneVoix' => $anciensVoix,
                            'nombreVoix' => $newDataVoix,
                            'bureau' => $freshBureau->getCode(),
                            'mode' => $modeEdition ? 'UPDATE' : 'CREATE'
                        ]
                    );

                    $this->addFlash('success', 'Votre modification a été prise en compte !');
                    return $this->redirectToRoute('app_assesseur_dashboard');

                } catch (\Exception $e) {
                    $em->getConnection()->rollBack();
                    $loggerInterface->error("Transaction Error: " . $e->getMessage());
                    $this->addFlash('error', 'Erreur Transaction : ' . $e->getMessage());
                }
            }
        }

        return $this->render('assesseur/resultat.html.twig', [
            'form' => $form,
            'bureau' => $bureau,
        ], new Response(null, ($form->isSubmitted() && !$form->isValid()) ? 422 : 200));
    }

    #[Route('/observations', name: 'app_assesseur_observations', methods: ['GET', 'POST'])]
    public function observations(Request $request, EntityManagerInterface $em, LogsRepository $logsRepository, TokenInterface $token, ObservationRepository $observationRepository): Response
    {
        /** @var User $user */
        $user = $token->getUser();
        if (!$user)
            return $this->redirectToRoute('app_login');

        $managedBureau = $user->getAssignedBureau();
        if (!$managedBureau) {
            $this->addFlash('error', 'Vous n\'êtes assigné à aucun bureau de vote.');
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

            $this->addFlash('success', 'Observation enregistrée.');
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
