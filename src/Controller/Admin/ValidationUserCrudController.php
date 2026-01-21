<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\HttpFoundation\RequestStack;

class ValidationUserCrudController extends AbstractCrudController
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Validation Inscrit')
            ->setEntityLabelInPlural('File de Validation')
            ->setPageTitle('index', 'Inscriptions à valider (Assesseurs)')
            ->setPageTitle('edit', 'Valider et Assigner un Centre')
            ->setSearchFields(['nom', 'prenom', 'email', 'commune', 'arrondissement'])
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW , Action::DELETE) // Pas de création ni suppression
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::EDIT, 'ROLE_SUPERVISEUR')
            ->setPermission(Action::INDEX, 'ROLE_SUPERVISEUR');
    }

    public function configureFields(string $pageName): iterable
    {
        // Champs en lecture seule pour identifier la personne
        yield TextField::new('fullName', 'Nom & Prénom')->hideOnForm();
        yield TextField::new('email', 'Email')->setDisabled();


        // Info de localisation fournie par l'user
        yield TextField::new('adresse', 'Adresse')->setDisabled();

        // Champs à éditer par le Superviseur
        yield AssociationField::new('assignedCentre', 'Centre de Vote à Assigner')
            ->autocomplete()
            ->setHelp('Recherchez le centre correspond aux informations de l\'utilisateur.');

        yield BooleanField::new('isVerified', 'Email Vérifié')
            ->renderAsSwitch()
            ->setDisabled(); // On ne touche pas à l'email, c'est auto

        // Le superviseur promeut l'utilisateur en changeant son rôle
        yield ChoiceField::new('roles', 'Statut / Rôle')
            ->setChoices([
                'Accès Standard (Inscrit)' => 'ROLE_USER',
                'Accès Assesseur (Validé)' => 'ROLE_ASSESSEUR',
            ])
            ->allowMultipleChoices()
            ->renderAsBadges()
            ->setHelp('Cochez "Accès Assesseur" pour valider le compte.');
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $user = $this->getUser();

        // Si c'est un Admin global, il voit tout
        if ($this->isGranted('ROLE_ADMIN')) {
            return $qb;
        }

        // Si c'est un Superviseur, il ne voit que les gens:
        // 1. De son département (si défini)
        if ($user instanceof User && $user->getDepartement()) {
            $qb->andWhere('entity.departement = :dept')
                ->setParameter('dept', $user->getDepartement());
        }

        // Exclure soi-même de la liste pour ne pas se valider
        if ($user) {
            $qb->andWhere('entity.id != :current_user_id')
                ->setParameter('current_user_id', $user->getId());
        }

        return $qb;
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            parent::updateEntity($entityManager, $entityInstance);
            return;
        }

        $shouldSendEmail = false;

        try {
            // Comparaison manuelle fiable via UnitOfWork
            $uow = $entityManager->getUnitOfWork();
            $originalData = $uow->getOriginalEntityData($entityInstance);

            $oldCentre = $originalData['assignedCentre'] ?? null;
            $newCentre = $entityInstance->getAssignedCentre();

            // Comparaison des IDs pour éviter les problèmes d'objets Proxy
            $oldCentreId = $oldCentre ? $oldCentre->getId() : null;
            $newCentreId = $newCentre ? $newCentre->getId() : null;

            $this->logger->info('ValidationDebug: Checking centre change', [
                'user' => $entityInstance->getEmail(),
                'oldCentreId' => $oldCentreId,
                'newCentreId' => $newCentreId
            ]);

            // Si un nouveau centre est assigné (et différent de l'ancien)
            if ($newCentreId !== null && $newCentreId !== $oldCentreId) {
                $shouldSendEmail = true;
                $this->logger->info('ValidationDebug: Change detected -> Email flag set to true.');
            }

        } catch (\Exception $e) {
            $this->logger->error('ValidationDebug: Error checking changes', ['error' => $e->getMessage()]);
        }

        parent::updateEntity($entityManager, $entityInstance);

        if ($shouldSendEmail) {
            $this->sendAssignmentEmail($entityInstance);
            $this->logger->info('ValidationDebug: Email sent to ' . $entityInstance->getEmail());
        }
    }

    private function sendAssignmentEmail(User $user): void
    {
        $senderEmail = $_ENV['MAILER_FROM'] ?? 'no-reply@surevote.bj';

        $email = (new TemplatedEmail())
            ->from(new Address($senderEmail, 'SureVote Supervision'))
            ->to($user->getEmail())
            ->subject('✅ Votre centre de vote a été assigné')
            ->htmlTemplate('emails/assignment.html.twig')
            ->context([
                'user' => $user,
                'centre' => $user->getAssignedCentre(),
            ]);

        try {
            $this->mailer->send($email);
            $this->logger->info('ValidationUserCrudController: Assignment email sent to ' . $user->getEmail());
        } catch (\Throwable $e) {
            $this->logger->error('ValidationUserCrudController: Failed to send assignment email', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
