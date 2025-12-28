<?php

namespace App\Controller\Admin;

use App\Entity\CentreDeVote;
use App\Entity\Circonscription;
use App\Form\ImportType;
use App\Repository\LogsRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/import')]
#[IsGranted('ROLE_ADMIN')]
class ImportController extends AbstractController
{
    #[Route('/circonscriptions', name: 'app_admin_import_circonscriptions', methods: ['GET', 'POST'])]
    public function importCirconscriptions(Request $request, EntityManagerInterface $em, LogsRepository $logsRepository): Response
    {
        $form = $this->createForm(ImportType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();

            if ($file) {
                try {
                    $spreadsheet = IOFactory::load($file->getPathname());
                    $worksheet = $spreadsheet->getActiveSheet();
                    $rows = $worksheet->toArray();

                    $errors = [];
                    $importedCount = 0;
                    $isFirstRow = true;
                    $rowIndex = 1;

                    foreach ($rows as $data) {
                        $rowIndex++;
                        if ($isFirstRow) {
                            $isFirstRow = false;
                            continue;
                        }

                        // Structure attendue : COL 0 = Nom, COL 1 = Code, COL 2 = Villes (séparées par |)
                        $nom = trim($data[0] ?? '');
                        $code = trim($data[1] ?? '');
                        $villesRaw = trim($data[2] ?? '');

                        if (empty($nom) || empty($code)) {
                            $errors[] = "Ligne $rowIndex : Nom ou Code manquant";
                            continue;
                        }

                        $existing = $em->getRepository(Circonscription::class)->findOneBy(['code' => $code]);
                        if ($existing) {
                            $errors[] = "Ligne $rowIndex : Code '$code' déjà existant (doublon ignoré)";
                            continue;
                        }

                        $circonscription = new Circonscription();
                        $circonscription->setNom($nom);
                        $circonscription->setCode($code);

                        if (!empty($villesRaw)) {
                            $villes = array_filter(array_map('trim', explode('|', $villesRaw)));
                            $circonscription->setVilles(array_values($villes));
                        }

                        $em->persist($circonscription);
                        $importedCount++;

                        if ($importedCount % 20 === 0) {
                            $em->flush();
                            $em->clear();
                        }
                    }

                    $em->flush();

                    $logsRepository->logAction(
                        action: 'IMPORT_CIRCONSCRIPTIONS',
                        user: $this->getUser(),
                        ip: $request->getClientIp(),
                        userAgent: $request->headers->get('User-Agent'),
                        details: ['count' => $importedCount, 'errors' => count($errors), 'format' => pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION)]
                    );

                    if ($importedCount > 0) {
                        $this->addFlash('success', "$importedCount circonscriptions importées avec succès !");
                    } else {
                        $this->addFlash('warning', "Aucune circonscription n'a été importée.");
                    }

                    if (count($errors) > 0) {
                        $msg = implode('<br>', array_slice($errors, 0, 5));
                        if (count($errors) > 5) {
                            $msg .= '<br>... et ' . (count($errors) - 5) . ' autres erreurs.';
                        }
                        $this->addFlash('danger', "<strong>" . count($errors) . " erreurs rencontrées :</strong><br>" . $msg);
                    }

                    return $this->redirectToRoute('admin');

                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Erreur lors de la lecture du fichier : ' . $e->getMessage());
                }
            }
        }

        return $this->render('admin/import/circonscriptions.html.twig', [
            'form' => $form->createView(),
            'title' => 'Importer des Circonscriptions'
        ]);
    }

    #[Route('/centres-de-vote', name: 'app_admin_import_centres', methods: ['GET', 'POST'])]
    public function importCentresDeVote(Request $request, EntityManagerInterface $em, LogsRepository $logsRepository): Response
    {
        $form = $this->createForm(ImportType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();

            if ($file) {
                try {
                    $spreadsheet = IOFactory::load($file->getPathname());
                    $worksheet = $spreadsheet->getActiveSheet();
                    $rows = $worksheet->toArray();

                    $importedCount = 0;
                    $isFirstRow = true;

                    // Cache des IDs de Circonscription
                    $circoMap = [];
                    foreach ($em->getRepository(Circonscription::class)->findAll() as $c) {
                        if ($c->getCode()) {
                            $circoMap[strtoupper(trim($c->getCode()))] = $c->getId();
                        }
                    }

                    $errors = [];
                    $rowIndex = 1;

                    foreach ($rows as $data) {
                        $rowIndex++;
                        if ($isFirstRow) {
                            $isFirstRow = false;
                            continue;
                        }

                        $nom = trim($data[0] ?? '');
                        $code = trim($data[1] ?? '');
                        $codeCirco = strtoupper(trim($data[2] ?? ''));

                        if (empty($nom) || empty($code) || empty($codeCirco)) {
                            $errors[] = "Ligne $rowIndex : Données incomplètes (Nom, Code, CodeCirco requis)";
                            continue;
                        }

                        if (!isset($circoMap[$codeCirco])) {
                            $errors[] = "Ligne $rowIndex : Circonscription '$codeCirco' inconnue";
                            continue;
                        }

                        if ($em->getRepository(CentreDeVote::class)->findOneBy(['code' => $code])) {
                            $errors[] = "Ligne $rowIndex : Centre '$code' déjà existant (doublon ignoré)";
                            continue;
                        }

                        $centre = new CentreDeVote();
                        $centre->setNom($nom);
                        $centre->setCode($code);
                        $centre->setCirconscription($em->getReference(Circonscription::class, $circoMap[$codeCirco]));

                        if (!empty($data[3]))
                            $centre->setAdresse(trim($data[3]));
                        if (!empty($data[4]))
                            $centre->setLatitude((float) str_replace(',', '.', $data[4]));
                        if (!empty($data[5]))
                            $centre->setLongitude((float) str_replace(',', '.', $data[5]));

                        $em->persist($centre);
                        $importedCount++;

                        if ($importedCount % 50 === 0) {
                            $em->flush();
                            $em->clear();
                        }
                    }

                    $em->flush();
                    $logsRepository->logAction('IMPORT_CENTRES', $this->getUser(), $request->getClientIp(), $request->headers->get('User-Agent'), ['count' => $importedCount, 'errors' => count($errors)]);

                    if ($importedCount > 0) {
                        $this->addFlash('success', "$importedCount centres importés avec succès !");
                    } else {
                        $this->addFlash('warning', "Aucun centre n'a été importé.");
                    }

                    if (count($errors) > 0) {
                        $msg = implode('<br>', array_slice($errors, 0, 5));
                        if (count($errors) > 5) {
                            $msg .= '<br>... et ' . (count($errors) - 5) . ' autres erreurs.';
                        }
                        $this->addFlash('danger', "<strong>" . count($errors) . " erreurs rencontrées :</strong><br>" . $msg);
                    }
                    return $this->redirectToRoute('admin');

                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Erreur import : ' . $e->getMessage());
                }
            }
        }

        return $this->render('admin/import/centres_de_vote.html.twig', [
            'form' => $form->createView(),
            'title' => 'Importer des Centres de Vote'
        ]);
    }
}
