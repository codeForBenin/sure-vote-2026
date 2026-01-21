<?php

namespace App\Controller\Admin;

use App\Entity\Circonscription;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

class CirconscriptionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Circonscription::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('une circonscription')
            ->setEntityLabelInPlural('Liste des circonscriptions')
            ->setPageTitle('detail', fn(Circonscription $circonscription) => sprintf('Circonscription : %s', $circonscription->getNom()))
            ->setDefaultSort(['code' => 'ASC'])
            ->setPaginatorPageSize(25)
            ->showEntityActionsInlined()
        ;
    }

    private AdminUrlGenerator $adminUrlGenerator;

    public function __construct(AdminUrlGenerator $adminUrlGenerator)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public function configureActions(Actions $actions): Actions
    {
        $importAction = Action::new('import', 'Importer CSV')
            ->linkToRoute('app_admin_import_circonscriptions')
            ->createAsGlobalAction() // Affiche le bouton en haut de la liste
            ->setCssClass('btn btn-outline-primary')
            ->setIcon('fa fa-upload');

        $viewResults = Action::new('viewResults', 'Voir Résultats PV', 'fa fa-chart-bar')
            ->linkToUrl(function (Circonscription $entity) {
                return $this->adminUrlGenerator
                    ->setController(ResultatCrudController::class)
                    ->setAction('index')
                    ->addSignature(true)
                    ->generateUrl();
            })
            ->setCssClass('btn btn-sm btn-info');

        return $actions
            ->add(Crud::PAGE_INDEX, $importAction)
            ->add(Crud::PAGE_INDEX, $viewResults);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('nom', 'Nom de la circonscription'),
            TextField::new('code', 'Code unique'),
            TextField::new('departement', 'Département'),
            IntegerField::new('sieges', 'Sièges'),
            IntegerField::new('population', 'Population'),
            ArrayField::new('villes', 'Villes / Communes'),
        ];
    }
}
