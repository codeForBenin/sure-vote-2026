<?php

namespace App\Controller\Admin;

use App\Entity\BroadcastMessage;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class BroadcastMessageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return BroadcastMessage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Message (Diffusion)')
            ->setEntityLabelInPlural('Messages aux Assesseurs')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm()->hideOnIndex(),

            TextField::new('titre', 'Titre / Objet')
                ->setHelp('Ex: Consigne horaire, Rappel sécurité...'),

            ChoiceField::new('type', 'Importance')
                ->setChoices([
                    'Information' => 'INFO',
                    'Important' => 'IMPORTANT',
                    'Urgent' => 'URGENT'
                ])
                ->renderAsBadges([
                    'INFO' => 'info',
                    'IMPORTANT' => 'warning',
                    'URGENT' => 'danger'
                ]),

            TextareaField::new('message', 'Contenu du message'),

            BooleanField::new('active', 'Actif')
                ->setHelp('Si actif, ce message sera visible sur le dashboard des assesseurs.'),

            DateTimeField::new('createdAt', 'Créé le')->hideOnForm(),
        ];
    }
}
