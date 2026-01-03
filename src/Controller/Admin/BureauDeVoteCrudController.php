<?php

namespace App\Controller\Admin;

use App\Entity\BureauDeVote;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

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

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('nom')->setFormTypeOption('label', 'Nom'))
            ->add(TextFilter::new('code')->setFormTypeOption('label', 'Code'))
            ->add(EntityFilter::new('centre'));
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
