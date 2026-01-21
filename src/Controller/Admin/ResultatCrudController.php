<?php

namespace App\Controller\Admin;

use App\Entity\Resultat;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class ResultatCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Resultat::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Résultat PV')
            ->setEntityLabelInPlural('Résultats PV')
            ->setPageTitle('index', 'Liste des résultats des procès-verbaux')
            ->setPageTitle('detail', 'Détails du résultat')
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('bureauDeVote', 'Bureau de Vote'))
            ->add(EntityFilter::new('parti', 'Parti Politique'))
            ->add(EntityFilter::new('assesseur', 'Assesseur'))
            ->add(BooleanFilter::new('isValidated', 'Validé'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $exportAction = Action::new('export', 'Exporter CSV', 'fa fa-download')
            ->linkToUrl(function () {
                return $this->generateUrl('app_admin_resultats_export');
            })
            ->createAsGlobalAction()
            ->setCssClass('btn btn-success');

        return $actions
            // Disable manual creation - results should only be created by assesseurs
            ->disable(Action::NEW)
            // Edit is allowed mainly for validation
            ->disable(Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $exportAction);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('bureauDeVote', 'Bureau de Vote')
                ->formatValue(function ($value, $entity) {
                    $bureau = $entity->getBureauDeVote();
                    if (!$bureau)
                        return '-';

                    return sprintf(
                        '<span class="badge badge-info">%s</span> <small class="text-muted">%s</small>',
                        $bureau->getCode(),
                        $bureau->getNom()
                    );
                })
                ->setTemplatePath('admin/field/html.html.twig'),

            AssociationField::new('parti', 'Parti Politique')
                ->formatValue(function ($value, $entity) {
                    $parti = $entity->getParti();
                    if (!$parti)
                        return '-';

                    return sprintf(
                        '<strong>%s</strong> <span class="badge badge-light">%s</span>',
                        $parti->getNom(),
                        $parti->getSigle() ?? ''
                    );
                })
                ->setTemplatePath('admin/field/html.html.twig'),

            IntegerField::new('nombreVoix', 'Nombre de voix')
                ->formatValue(function ($value) {
                    return sprintf('<span class="badge badge-success" style="font-size: 1.1em;">%d</span>', $value);
                })
                ->setTemplatePath('admin/field/html.html.twig'),

            TextField::new('pvImageName', 'Preuve PV')
                ->setTemplatePath('admin/field/vich_image.html.twig')
                ->setSortable(false),

            AssociationField::new('assesseur', 'Assesseur')
                ->formatValue(function ($value, $entity) {
                    $assesseur = $entity->getAssesseur();
                    if (!$assesseur)
                        return '-';

                    return sprintf(
                        '<i class="fa fa-user-shield text-primary"></i> <strong>%s %s</strong>',
                        $assesseur->getNom(),
                        $assesseur->getPrenom()
                    );
                })
                ->setTemplatePath('admin/field/html.html.twig'),

            BooleanField::new('isValidated', 'Validé'),

            DateTimeField::new('updatedAt', 'Dernière mise à jour')
                ->formatValue(function ($value) {
                    if (!$value) {
                        return '<span class="text-muted">-</span>';
                    }
                    return sprintf(
                        '<i class="fa fa-clock text-info"></i> %s',
                        $value->format('d/M/Y à H:i')
                    );
                })
                ->setTemplatePath('admin/field/html.html.twig'),
        ];
    }
}
