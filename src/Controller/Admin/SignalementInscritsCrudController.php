<?php

namespace App\Controller\Admin;

use App\Entity\SignalementInscrits;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use App\Service\Election\InscritsUpdater;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
class SignalementInscritsCrudController extends AbstractCrudController
{
    private InscritsUpdater $updater;
    private MailerInterface $mailer;
    private LoggerInterface $loggerInterface;

    public function __construct(InscritsUpdater $updater, MailerInterface $mailer, LoggerInterface $loggerInterface)
    {
        $this->updater = $updater;
        $this->mailer = $mailer;
        $this->loggerInterface = $loggerInterface;
    }

    public static function getEntityFqcn(): string
    {
        return SignalementInscrits::class;
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof SignalementInscrits) {
            parent::updateEntity($entityManager, $entityInstance);
            return;
        }

        // Si le statut est VALIDATED, on applique les changements aux vrais bureaux
        if ($entityInstance->getStatut() === 'VALIDATED') {
            $this->updater->applyToEntities($entityInstance);

            // Envoi de l'email à l'assesseur
            if ($entityInstance->getAssesseur() && $entityInstance->getAssesseur()->getEmail()) {
                $email = (new TemplatedEmail())
                    ->from(new Address($_ENV['MAILER_FROM'], 'SureVote Validation'))
                    ->to($entityInstance->getAssesseur()->getEmail())
                    ->subject('Validation de votre déclaration d\'inscrits')
                    ->htmlTemplate('emails/assesseur_signalement_validation.html.twig')
                    ->context([
                        'signalement' => $entityInstance
                    ]);
                try {
                    $this->mailer->send($email);
                    $this->loggerInterface->info("Email envoyé", [
                        'signalement' => $entityInstance,
                        'email' => $email
                    ]);
                } catch (\Exception $e) {
                    $this->loggerInterface->error("Email non envoyé", [
                        'signalement' => $entityInstance,
                        'email' => $email,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Ajout d'un flash message pour confirmer
            $this->addFlash('success', 'Les inscrits ont été mis à jour dans le centre et les bureaux. Email envoyé à l\'assesseur.');
        } else {
            // Si le statut repasse à PENDING ou est REJETÉ, on réinitialise les bureaux
            $this->updater->resetEntities($entityInstance);
            $this->addFlash('warning', 'Le statut a changé (non validé). Les inscrits des bureaux correspondants ont été remis à 0.');
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('un signalement')
            ->setEntityLabelInPlural('Liste des signalements des inscrits des centres de vote')
            ->setPageTitle('detail', fn(SignalementInscrits $signalement) => sprintf('Signalement de %s pour le centre de vote : %s', $signalement->getAuteurNom(), $signalement->getCentreDeVote()->getNom()));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            TextField::new('auteurNom', 'Auteur')->hideOnDetail()->setDisabled(),
            AssociationField::new('centreDeVote')->hideOnDetail()->setDisabled(),
            IntegerField::new('nombreInscritsTotal', 'Total Inscrits'),
            CodeEditorField::new('repartitionBureaux', 'Détail Bureaux')
                ->hideOnIndex()
                ->setTemplatePath('admin/field/repartition_details.html.twig')
                ->hideOnForm(),
            TextField::new('preuveImageName', 'Preuve')
                ->setTemplatePath('admin/field/signalement_inscrits_image.html.twig')
                ->hideOnForm(),
            ChoiceField::new('statut')
                ->setChoices([
                    'En attente' => 'PENDING',
                    'Validé' => 'VALIDATED',
                    'Rejeté' => 'REJECTED'
                ])
                ->renderAsBadges([
                    'PENDING' => 'warning',
                    'VALIDATED' => 'success',
                    'REJECTED' => 'danger'
                ]),
            DateTimeField::new('createdAt', 'Date')->hideOnForm(),
        ];
    }
}
