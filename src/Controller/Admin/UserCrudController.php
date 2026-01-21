<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;

class UserCrudController extends AbstractCrudController
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher)
    {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setPageTitle('detail', fn(User $user) => sprintf('Utilisateur : %s %s', $user->getNom(), $user->getPrenom()));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            // Champs en lecture seule pour identifier la personne
            TextField::new('fullName', 'Nom & Prénom')->hideOnForm(),
            TextField::new('email', 'Email')->setDisabled(),

            // Info de localisation fournie par l'user
            TextField::new('adresse', 'Adresse')->hideOnForm(),
            BooleanField::new('isVerified', 'Vérifié')
                ->renderAsSwitch(),

            TextField::new('plainPassword', 'Mot de passe')
                ->setFormType(PasswordType::class)
                ->onlyOnForms()
                ->setRequired($pageName === Crud::PAGE_NEW)
                ->setHelp($pageName === Crud::PAGE_EDIT ? 'Laissez vide pour conserver le mot de passe actuel' : ''),

            ChoiceField::new('roles', 'Rôles')
                ->setChoices([
                    'Administrateur' => 'ROLE_ADMIN',
                    'Assesseur' => 'ROLE_ASSESSEUR',
                    'Superviseur' => 'ROLE_SUPERVISEUR',
                    'Utilisateur Simple' => 'ROLE_USER',
                ])
                ->allowMultipleChoices()
                ->renderAsBadges(),

            ChoiceField::new('departement', 'Département (Zone de supervision)')
                ->setChoices([
                    'Alibori' => 'Alibori',
                    'Atacora' => 'Atacora',
                    'Atlantique' => 'Atlantique',
                    'Borgou' => 'Borgou',
                    'Collines' => 'Collines',
                    'Couffo' => 'Couffo',
                    'Donga' => 'Donga',
                    'Littoral' => 'Littoral',
                    'Mono' => 'Mono',
                    'Ouémé' => 'Ouémé',
                    'Plateau' => 'Plateau',
                    'Zou' => 'Zou',
                ])
                ->setHelp('Pour un Superviseur, définit la zone qu\'il gère.'),

            AssociationField::new('assignedCentre', 'Centre de Vote Affecté')
                ->autocomplete()
                ->setHelp('L\'utilisateur sera assesseur ou superviseur pour ce centre.'),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashPassword($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashPassword($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function hashPassword($user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->getPlainPassword()) {
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $user->getPlainPassword())
            );
            $user->eraseCredentials();
        }
    }
}
