<?php

namespace App\Controller\Admin;

use App\Entity\BureauDeVote;
use App\Entity\CentreDeVote;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class CentreDeVoteCrudController extends AbstractCrudController
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;

    public function __construct(MailerInterface $mailer, LoggerInterface $logger)
    {
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    public static function getEntityFqcn(): string
    {
        return CentreDeVote::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('un centre de vote')
            ->setEntityLabelInPlural('Liste des centres de vote')
            ->setPaginatorPageSize(50)
            ->setDefaultSort(['nom' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('id')->hideOnForm()->hideOnIndex(),
            TextField::new('nom', 'Nom'),
            TextField::new('code', 'Code')->hideOnIndex(),
            TextField::new('departement', 'Département'),
            TextField::new('commune', 'Commune'),
            TextField::new('arrondissement', 'Arrondissement'),
            IntegerField::new('nombreBureauxReels', 'Bureaux Réels (Signalé)')->setColumns(6),
            BooleanField::new('isConfigValidated', 'Config Validée?')->renderAsSwitch(false)->setColumns(6),
            IdField::new('latitude', 'Latitude')->hideOnIndex(),
            IdField::new('longitude', 'Longitude')->hideOnIndex(),
        ];
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof CentreDeVote) {
            parent::updateEntity($entityManager, $entityInstance);
            return;
        }

        // Logic sync Bureaux
        if ($entityInstance->isConfigValidated() && $entityInstance->getNombreBureauxReels() !== null) {
            $currentBureaux = $entityInstance->getBureaux();
            $currentCount = count($currentBureaux);
            $targetCount = $entityInstance->getNombreBureauxReels();

            if ($targetCount > $currentCount) {
                // ADD
                // 1. Analyze existing bureaus to find the naming pattern and the highest PV number
                $prefix = $entityInstance->getCode(); // Default fallback
                $maxPvNum = 0;
                $existingCodes = []; // Track existing codes to avoid collision
                $bureauRepo = $entityManager->getRepository(BureauDeVote::class);

                foreach ($currentBureaux as $bureau) {
                    $existingCodes[] = $bureau->getCode();
                    // Try to extract number from code (XXX-PV1, XXX-PV2...)
                    if (preg_match('/^(.*)-PV(\d+)$/i', $bureau->getCode(), $matches)) {
                        $prefix = $matches[1]; // Keep the prefix of existing bureaus
                        $num = (int) $matches[2];
                        if ($num > $maxPvNum) {
                            $maxPvNum = $num;
                        }
                    }
                }

                // If no PVs exist or regex failed, maxPvNum is 0 (or count). 
                // However, maxPvNum is used for CODE uniquess base.
                if ($maxPvNum === 0) {
                    $maxPvNum = $currentCount;
                }

                $diff = $targetCount - $currentCount;
                for ($i = 1; $i <= $diff; $i++) {
                    $newNum = $maxPvNum + $i;

                    // Ensure GLOBAL uniqueness for the CODE
                    $candidateCode = $prefix . "-PV" . $newNum;
                    while (in_array($candidateCode, $existingCodes) || $bureauRepo->findOneBy(['code' => $candidateCode])) {
                        $newNum++;
                        $candidateCode = $prefix . "-PV" . $newNum;
                    }

                    // Ensure SEQUENTIAL naming for the DISPLAY NAME (PV 01, PV 02...) relative to this center
                    $nameNum = $currentCount + $i;
                    $formattedNameNum = str_pad((string) $nameNum, 2, '0', STR_PAD_LEFT);

                    $bureau = new BureauDeVote();
                    $bureau->setCentre($entityInstance);
                    $bureau->setNom("PV " . $formattedNameNum); // Visual: PV 01, PV 02...
                    $bureau->setCode($candidateCode); // Technical Unique: AGUIDI-...-PV5
                    $bureau->setNombreInscrits(0);

                    // Add new code to tracking list
                    $existingCodes[] = $candidateCode;

                    $entityManager->persist($bureau);
                }


                $this->addFlash('success', "$diff bureau(x) ajouté(s) automatiquement.");

            } elseif ($targetCount < $currentCount) {
                // REMOVE (Last ones)
                // Need to sort to remove high numbers first
                $sortedBureaux = $currentBureaux->toArray();
                usort($sortedBureaux, function ($a, $b) {
                    return strcmp($b->getCode(), $a->getCode()); // Descending by code
                });

                $diff = $currentCount - $targetCount;
                $removedCount = 0;

                for ($i = 0; $i < $diff; $i++) {
                    if (isset($sortedBureaux[$i])) {
                        $entityManager->remove($sortedBureaux[$i]);
                        $removedCount++;
                    }
                }
                $this->addFlash('warning', "$removedCount bureau(x) supprimé(s).");
            }

            // Notify Assessors linked to this center
            try {
                $this->notifyAssessors($entityInstance, $entityManager);
            } catch (\Exception $e) {
                $this->logger->error("Failed to notify assessors: " . $e->getMessage());
            }
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function notifyAssessors(CentreDeVote $centre, EntityManagerInterface $em): void
    {
        $users = $em->getRepository(User::class)->findBy(['assignedCentre' => $centre]);
        $emails = [];
        foreach ($users as $user) {
            if ($user->getEmail()) {
                $emails[] = new Address($user->getEmail(), $user->getFullName());
            }
        }

        if (empty($emails))
            return;

        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['FROM_EMAIL'] ?? 'no-reply@surevote.bj', 'SureVote Notification'))
            ->to(...$emails)
            ->subject('Mise à jour de la configuration de votre centre')
            ->htmlTemplate('emails/internal_communication.html.twig') // Better fit if generic
            ->context([
                'title' => 'Configuration Validée',
                'message' => sprintf(
                    "La configuration du centre %s a été validée et mise à jour.\nNombre de postes de vote actuel : %d.",
                    $centre->getNom(),
                    $centre->getNombreBureauxReels()
                ),
                'user_name' => 'Cher Assesseur'
            ]);

        $this->mailer->send($email);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('nom'))
            ->add(TextFilter::new('code'))
            ->add(TextFilter::new('commune'))
            ->add(TextFilter::new('arrondissement'))
            ->add(ChoiceFilter::new('departement')->setChoices([
                'Alibori' => 'Alibori',
                'Atacora' => 'Atacora',
                'Atlantique' => 'Atlantique',
                'Borgou' => 'Borgou',
                'Collines' => 'Collines',
                'Couffo' => 'Couffo',
                'Donga' => 'Donga',
                'Littoral' => 'Littoral',
                'Mono' => 'Mono',
                'Ouémé' => 'Ouémé',
                'Plateau' => 'Plateau',
                'Zou' => 'Zou',
            ]));
    }

    // public function configureActions(Actions $actions): Actions
    // {
    //     $importAction = Action::new('import', 'Importer CSV')
    //         ->linkToRoute('app_admin_import_centres')
    //         ->createAsGlobalAction() // Affiche le bouton en haut de la liste
    //         ->setCssClass('btn btn-primary')
    //         ->setIcon('fa fa-upload');

    //     return $actions
    //         ->add(Crud::PAGE_INDEX, $importAction);
    // }
}
