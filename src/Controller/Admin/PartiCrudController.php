<?php

namespace App\Controller\Admin;

use App\Entity\Parti;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;


class PartiCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Parti::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('nom', 'Nom du parti'),
            TextField::new('sigle', 'Sigle'),
            ColorField::new('couleur', 'Couleur de référence'),
            ChoiceField::new('affiliation', 'Affiliation politique')
                ->setChoices([
                    'Mouvance' => 'Mouvance',
                    'Opposition' => 'Opposition',
                    'Coalition gouvernementale' => 'Coalition gouvernementale',
                ])
                ->renderAsBadges([
                    'Mouvance' => 'success',
                    'Opposition' => 'warning',
                    'Coalition gouvernementale' => 'info',
                ]),
            ImageField::new('logoUrl', 'Logo')
                ->setBasePath('uploads/logos')
                ->setUploadDir('public/uploads/logos')
                ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
                ->setRequired(false),
        ];
    }
}
