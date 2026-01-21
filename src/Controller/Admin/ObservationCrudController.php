<?php

namespace App\Controller\Admin;

use App\Entity\BureauDeVote;
use App\Entity\Observation;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Vich\UploaderBundle\Form\Type\VichImageType;

class ObservationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Observation::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('une alerte')
            ->setEntityLabelInPlural('Remontées des observations')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->showEntityActionsInlined();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('niveau')->setChoices([
                'Normal' => 'INFO',
                'Urgent' => 'URGENT',
            ]))
            ->add(EntityFilter::new('bureauDeVote'))
            ->add(EntityFilter::new('assesseur'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW , Action::EDIT) // Observations are read-only for admin usually
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            DateTimeField::new('createdAt', 'Date')->setFormat('dd/MM/yyyy HH:mm'),
            TextField::new('imageName', 'Preuve')
                ->setTemplatePath('admin/field/observation_image.html.twig'),
            ChoiceField::new('niveau', 'Niveau')
                ->setChoices([
                    'Normal' => 'INFO',
                    'Urgent' => 'URGENT',
                ])
                ->renderAsBadges([
                    'INFO' => 'info',
                    'URGENT' => 'danger',
                ]),
            AssociationField::new('centreDeVote', 'Centre de vote concerné')
                ->formatValue(function (mixed $value, Observation $entity) {
                    $centreDeVote = $entity->getCentreDeVote();
                    if (!$centreDeVote)
                        return '-';

                    return sprintf(
                        '<span class="badge badge-info">%s</span> <small class="text-muted">%s</small>',
                        $centreDeVote->getCode(),
                        $centreDeVote->getNom()
                    );
                })
                ->setTemplatePath('admin/field/html.html.twig'),
            AssociationField::new('assesseur', 'Assesseur')
                ->formatValue(function (mixed $value, Observation $entity) {
                    $assesseur = $entity->getAssesseur();
                    if (!$assesseur)
                        return '-';

                    return sprintf(
                        '<i class="fa fa-user-shield text-primary"></i> <strong>%s %s</strong><br><small class="text-muted">%s</small>',
                        $assesseur->getNom(),
                        $assesseur->getPrenom(),
                        $assesseur->getEmail()
                    );
                })
                ->setTemplatePath('admin/field/html.html.twig'),
            TextEditorField::new('contenu', 'Message')
                ->hideOnIndex(),
        ];
    }
}
