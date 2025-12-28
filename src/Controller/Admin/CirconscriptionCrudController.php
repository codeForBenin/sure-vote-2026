<?php

namespace App\Controller\Admin;

use App\Entity\Circonscription;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class CirconscriptionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Circonscription::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $importAction = Action::new('import', 'Importer CSV')
            ->linkToRoute('app_admin_import_circonscriptions')
            ->createAsGlobalAction() // Affiche le bouton en haut de la liste
            ->setCssClass('btn btn-primary')
            ->setIcon('fa fa-upload');

        return $actions
            ->add(Crud::PAGE_INDEX, $importAction);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('nom', 'Nom de la circonscription'),
            TextField::new('code', 'Code unique'),
            ArrayField::new('villes', 'Villes / Communes')->hideOnIndex(),
        ];
    }
}
