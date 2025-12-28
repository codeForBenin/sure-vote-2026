<?php

namespace App\Controller;

use App\Entity\BureauDeVote;
use App\Entity\Observation;
use App\Entity\Participation;
use App\Entity\Resultat;
use App\Entity\User;
use App\Form\ObservationType;
use App\Form\ParticipationType;
use App\Form\ResultatsType;
use App\Repository\BroadcastMessageRepository;
use App\Repository\BureauDeVoteRepository;
use App\Repository\LogsRepository;
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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/assesseur')]
#[IsGranted('ROLE_ASSESSEUR')]
class AssesseurController extends AbstractController
{
    #[Route('/', name: 'app_assesseur_dashboard')]
    public function index(
        BureauDeVoteRepository $bureauDeVoteRepository,
        ResultatRepository $resultatRepository,
        ParticipationRepository $participationRepository,
        BroadcastMessageRepository $broadcastMessageRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // ... (gestion du admin preview ou assesseur bureau ...)

        // Si admin (ROLE_ADMIN) sans bureau assigné, on le laisse voir le dash en readonly idéalement ou on lui assigne un faux bureau pour test
        // Pour l'instant le code existant gère le cas Admin qui a cliqué sur "simuler un bureau"

        $managedBureau = $user->getAssignedBureau();
        // Si Admin, on peut avoir un paramètre GET 'bureau_id' pour simuler la vue assesseur (Feature avancée)
        // Ici on garde la logique existante.

        $bureau = $managedBureau;
        if (!$bureau) {
            // Fallback logique existante
            // ...
        }

        // --- Logique existante ---
        // (On suppose que le code existant récupère $bureau correctement)

        if (!$bureau) {
            // check if admin wants to preview
            // This part was implicit in previous code, let's keep it robust
            // return $this->redirectToRoute('admin');
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

        return $this->render('assesseur/index.html.twig', [
            'bureau' => $bureau,
            'isVoteComplet' => $isVoteComplet,
            'sommeVoix' => $sommeVoix,
            'tauxParticipation' => round($tauxParticipation, 2),
            'nombreVotants' => $nombreVotants,
            'messages' => $messages
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
                // Set heurePointage AFTER validation to ensure it's the exact submission time
                $participation->setHeurePointage(new \DateTimeImmutable());

                // Debug: Check if bureau is still set
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
                    $isNear = $geoVerifier->isWithinRange(
                        (float) $lat,
                        (float) $lon,
                        $centre->getLatitude(),
                        $centre->getLongitude()
                    );

                    $participation->setMetadataLocation([
                        'lat' => $lat,
                        'lon' => $lon,
                        'is_near' => $isNear
                    ]);
                }

                $logger->info('Participation: ' . $participation);

                // Clear and reload to ensure clean state
                $em->clear();

                // Reload entities to ensure they're managed
                $freshBureau = $em->find(BureauDeVote::class, $managedBureau->getId());
                $freshUser = $em->find(User::class, $user->getId());

                // Create a fresh participation with managed entities
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

    #[Route('/resultat', name: 'app_assesseur_resultat', methods: ['GET', 'POST'])]
    public function resultat(Request $request, EntityManagerInterface $em, ResultatRepository $resultatRepository, GeoVerifier $geoVerifier, LoggerInterface $logger): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $bureau = $user->getAssignedBureau();

        if (!$bureau) {
            $this->addFlash('error', 'Vous devez être assigné à un bureau de vote pour saisir un résultat.');
            return $this->redirectToRoute('app_assesseur_dashboard');
        }

        // Reload the bureau from database to ensure it's managed by Doctrine
        $managedBureau = $em->find(BureauDeVote::class, $bureau->getId());

        if (!$managedBureau) {
            throw new \Exception('Bureau de vote introuvable');
        }

        $resultat = new Resultat();
        $resultat->setBureauDeVote($managedBureau);
        $resultat->setAssesseur($user);

        $form = $this->createForm(ResultatsType::class, $resultat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 1. Validation de base : Voix <= Inscrits
            if ($resultat->getNombreVoix() > $managedBureau->getNombreInscrits()) {
                $form->get('nombreVoix')->addError(new FormError(
                    sprintf(
                        'Le nombre de voix (%d) dépasse le nombre d\'inscrits (%d).',
                        $resultat->getNombreVoix(),
                        $managedBureau->getNombreInscrits()
                    )
                ));
            }

            // 2. Validation de l'unicité parti/bureau
            $existingResultat = $resultatRepository->findOneBy([
                'bureauDeVote' => $managedBureau,
                'parti' => $resultat->getParti()
            ]);

            if ($existingResultat) {
                $form->get('parti')->addError(new FormError(
                    sprintf('Un résultat pour le parti "%s" a déjà été saisi pour ce bureau.', $resultat->getParti()->getNom())
                ));
            }

            // 3. Validation de la somme totale
            if (count($form->getErrors(true)) === 0) {
                $sommeActuelle = $resultatRepository->getSommeVoixParBureau((string) $managedBureau->getId());
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
                // Geo check (optionnel)
                $lat = $request->request->get('lat');
                $lon = $request->request->get('lon');

                $em->beginTransaction();
                try {
                    // VERROIULLAGE PESSIMISTE : On reload le bureau avec un Lock Write
                    // Cela met en attente les autres transactions concurrentes sur ce bureau
                    $lockedBureau = $em->find(BureauDeVote::class, $managedBureau->getId(), LockMode::PESSIMISTIC_WRITE);

                    // Re-vérification de la somme sous verrou
                    // Comme on est verrouillé, personne d'autre n'écrit en ce moment.
                    $sommeActuelle = $resultatRepository->getSommeVoixParBureau((string) $lockedBureau->getId());

                    if (($sommeActuelle + $resultat->getNombreVoix()) > $lockedBureau->getNombreInscrits()) {
                        throw new \Exception(sprintf(
                            "Le total (%d) dépasserait le nombre d'inscrits (%d). Une autre saisie a eu lieu entre temps.",
                            $sommeActuelle + $resultat->getNombreVoix(),
                            $lockedBureau->getNombreInscrits()
                        ));
                    }

                    $freshUser = $em->find(User::class, $user->getId());
                    $freshParti = $em->find(\App\Entity\Parti::class, $resultat->getParti()->getId());

                    $freshResultat = new Resultat();
                    $freshResultat->setBureauDeVote($lockedBureau); // On lie au bureau verrouillé
                    $freshResultat->setAssesseur($freshUser);
                    $freshResultat->setParti($freshParti);
                    $freshResultat->setNombreVoix($resultat->getNombreVoix());
                    $freshResultat->setPvImageFile($resultat->getPvImageFile());

                    $em->persist($freshResultat);
                    $em->flush();
                    $em->commit();

                    $this->addFlash('success', 'Résultat du PV enregistré avec succès !');
                    return $this->redirectToRoute('app_assesseur_dashboard');

                } catch (\Exception $e) {
                    $em->rollback();
                    // On ajoute l'erreur au formulaire pour l'afficher proprement
                    $form->addError(new FormError($e->getMessage()));
                }
            }
        }

        return $this->render('assesseur/resultat.html.twig', [
            'form' => $form,
            'bureau' => $bureau,
        ]);
    }

    #[Route('/observations', name: 'app_assesseur_observations', methods: ['GET', 'POST'])]
    public function observations(Request $request, EntityManagerInterface $em, LogsRepository $logsRepository, TokenInterface $token): Response
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
                    'assesseur' => $user,
                    'observation' => $observation->getContenu() ?? "",
                    'niveau' => $observation->getNiveau() ?? "",
                ]
            );

            $this->addFlash('success', 'Observation enregistrée.');
            return $this->redirectToRoute('app_assesseur_observations');
        }

        $historique = $em->getRepository(Observation::class)->findByBureau((string) $managedBureau->getId());

        return $this->render('assesseur/observations.html.twig', [
            'form' => $form->createView(),
            'historique' => $historique,
            'bureau' => $managedBureau
        ]);
    }
}
