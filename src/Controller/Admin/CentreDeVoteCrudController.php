<?php

namespace App\Controller\Admin;

use App\Entity\CentreDeVote;
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
            ->setEntityLabelInSingular('Centre de vote')
            ->setEntityLabelInPlural('Liste des centres de vote')
            ->setPaginatorPageSize(50)
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
}
