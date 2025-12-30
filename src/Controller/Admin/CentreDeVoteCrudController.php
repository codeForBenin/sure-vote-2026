<?php

namespace App\Controller\Admin;

use App\Entity\CentreDeVote;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CentreDeVoteCrudController extends AbstractCrudController
{
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
            ->setDefaultSort(['adresse' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('id')->hideOnForm()->hideOnIndex(),
            TextField::new('nom', 'Nom'),
            TextField::new('code', 'Code'),
            TextField::new('adresse', 'Adresse'),
            IdField::new('latitude', 'Latitude'),
            IdField::new('longitude', 'Longitude'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $importAction = Action::new('import', 'Importer CSV')
            ->linkToRoute('app_admin_import_centres')
            ->createAsGlobalAction() // Affiche le bouton en haut de la liste
            ->setCssClass('btn btn-primary')
            ->setIcon('fa fa-upload');

        return $actions
            ->add(Crud::PAGE_INDEX, $importAction);
    }
}
