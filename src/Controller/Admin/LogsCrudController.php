<?php

namespace App\Controller\Admin;

use App\Entity\Logs;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class LogsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Logs::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW , Action::EDIT, Action::DELETE) // Les logs sont immuables
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Log')
            ->setEntityLabelInPlural('Logs Système')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnIndex(),
            DateTimeField::new('createdAt', 'Date')
                ->setFormat('dd/MM/yyyy HH:mm:ss'),

            TextField::new('action', 'Action')
                ->formatValue(function ($value) {
                    $color = match ($value) {
                        'LOGIN' => 'success',
                        'PV_DOWNLOAD' => 'info',
                        'EXPORT_PARTICIPATION', 'EXPORT_RESULTAT' => 'warning',
                        'IMPORT_CIRCONSCRIPTIONS' => 'primary',
                        'ACCESS_DENIED' => 'danger',
                        'IMPORT_CENTRES' => 'primary',
                        'ERREUR_SYSTEME' => 'warning',
                        default => 'secondary'
                    };
                    return sprintf('<span class="badge badge-%s">%s</span>', $color, $value);
                })
                ->setTemplatePath('admin/field/html.html.twig'),

            AssociationField::new('user', 'Utilisateur'),
            TextField::new('ipAddress', 'IP'),

            TextField::new('userAgent', 'User Agent')
                ->hideOnIndex()
                ->setMaxLength(50),

            CodeEditorField::new('details', 'Détails (JSON)')
                ->setLanguage('php')
                ->hideOnIndex()
                ->formatValue(function ($value) {
                    if (is_array($value)) {
                        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    }
                    return $value;
                }),
        ];
    }
}
