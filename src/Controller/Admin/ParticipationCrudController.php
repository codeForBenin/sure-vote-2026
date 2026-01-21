<?php

namespace App\Controller\Admin;

use App\Entity\Participation;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class ParticipationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Participation::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Pointage de Participation')
            ->setEntityLabelInPlural('Pointages de Participation')
            ->setPageTitle('index', 'Liste des pointages de participation')
            ->setPageTitle('detail', 'Détails du pointage')
            ->setDefaultSort(['heurePointage' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $exportAction = Action::new('export', 'Exporter CSV', 'fa fa-download')
            ->linkToUrl(function () {
                return $this->generateUrl('app_admin_participation_export');
            })
            ->createAsGlobalAction()
            ->setCssClass('btn btn-success');

        return $actions
            // Désactiver la création manuelle - les participations ne doivent être créées que par les assesseurs
            // Désactiver l'édition pour maintenir l'intégrité des données
            // Garder la suppression pour le nettoyage administrateur si nécessaire
            ->add(Crud::PAGE_INDEX, $exportAction);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('bureauDeVote', 'Bureau de Vote'))
            ->add(EntityFilter::new('assesseur', 'Assesseur'))
            ->add(DateTimeFilter::new('heurePointage', 'Heure du pointage'))
        ;
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

            TextField::new('circonscription', 'Circonscription')
                ->formatValue(function ($value, $entity) {
                    $bureau = $entity->getBureauDeVote();
                    if (!$bureau || !$bureau->getCentre())
                        return '-';

                    return sprintf(
                        '<span class="badge badge-secondary">%s</span>',
                        $bureau->getCentre()->getCirconscription()->getNom()
                    );
                })
                ->setTemplatePath('admin/field/html.html.twig')
                ->onlyOnIndex(),

            AssociationField::new('assesseur', 'Assesseur')
                ->formatValue(function ($value, $entity) {
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

            IntegerField::new('nombreVotants', 'Nombre de votants')
                ->setTemplateName('crud/field/integer')
                ->formatValue(function ($value) {
                    return sprintf('<span class="badge badge-success" style="font-size: 1.1em;">%d</span>', $value);
                })
                ->setTemplatePath('admin/field/html.html.twig'),

            DateTimeField::new('heurePointage', 'Heure du pointage')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->formatValue(function ($value) {
                    return sprintf(
                        '<i class="fa fa-clock text-info"></i> %s',
                        $value->format('d/m/Y à H:i')
                    );
                })
                ->setTemplatePath('admin/field/html.html.twig'),

            DateTimeField::new('createdAt', 'Créé le')
                ->setFormat('dd/MM/yyyy HH:mm')
                ->onlyOnDetail(),
        ];
    }
}
