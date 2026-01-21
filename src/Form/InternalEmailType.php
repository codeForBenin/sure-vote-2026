<?php

namespace App\Form;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class InternalEmailType extends AbstractType
{
    private User $user;
    private UserRepository $userRepository;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        UserRepository $userRepository
    ) {
        $this->user = $tokenStorage->getToken()->getUser();
        $this->userRepository = $userRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', TextType::class, [
                'label' => 'Sujet',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Sujet de votre message']
            ])
            ->add('sendToAll', CheckboxType::class, [
                'label' => 'Envoyer à tous les assesseurs',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('recipient', EntityType::class, [
                'class' => User::class,
                'choices' => $this->getAssesseurs(),
                'choice_label' => function (User $user) {
                    return $user->getFullName() . ' (' . $user->getEmail() . ')';
                },
                'placeholder' => 'Choisir un destinataire spécifique (laisser vide si envoi groupé)',
                'required' => false,
                'label' => 'Destinataire (optionnel)',
                'attr' => ['class' => 'form-select']
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'attr' => ['rows' => 8, 'class' => 'form-control']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }

    private function getAssesseurs(): array
    {
        $allUsers = $this->userRepository->findAll();

        $filtered = array_filter($allUsers, function (User $u) {
            // S'auto exclure
            if ($u === $this->user) {
                return false;
            }
            // Contient le rôle ROLE_ASSESSEUR
            return in_array('ROLE_ASSESSEUR', $u->getRoles());
        });

        // Tri par nom
        usort($filtered, function (User $a, User $b) {
            return strcasecmp($a->getNom() ?? '', $b->getNom() ?? '');
        });

        return $filtered;
    }
}
