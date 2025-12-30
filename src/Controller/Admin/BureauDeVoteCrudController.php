<?php

namespace App\Controller\Admin;

use App\Entity\BureauDeVote;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class BureauDeVoteCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return BureauDeVote::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('un bureau de vote')
            ->setEntityLabelInPlural('Liste des bureaux de vote')
            ->setPaginatorPageSize(50)
            ->setDefaultSort(['code' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm()->hideOnIndex(),
            TextField::new('nom', 'Nom'),
            TextField::new('code', 'Code'),
            NumberField::new('nombreInscrits', 'Nombre d\'inscrits'),
            AssociationField::new('centre', 'Centre de vote'),
        ];
    }
}
