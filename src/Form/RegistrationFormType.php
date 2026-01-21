<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['placeholder' => 'Votre nom de famille']
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénoms',
                'attr' => ['placeholder' => 'Vos prénoms']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse Email',
                'attr' => ['placeholder' => 'exemple@email.com']
            ])
            ->add('departement', ChoiceType::class, [
                'label' => 'Département de résidence',
                'placeholder' => 'Choisir un département',
                'choices' => [
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
                ],
            ])
            ->add('commune', TextType::class, [
                'label' => 'Commune',
                'attr' => ['placeholder' => 'Ex: Cotonou, Abomey-Calavi...']
            ])
            ->add('arrondissement', TextType::class, [
                'label' => 'Arrondissement',
                'attr' => ['placeholder' => 'Ex: 13ème Arrondissement, Godomey...']
            ])
            ->add('lieuDeResidence', TextType::class, [
                'label' => 'Quartier / Village',
                'attr' => ['placeholder' => 'Votre quartier de résidence']
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'Mot de passe', 'attr' => ['autocomplete' => 'new-password', 'placeholder' => 'Votre mot de passe']],
                'second_options' => ['label' => 'Confirmer le mot de passe', 'attr' => ['placeholder' => 'Confirmer votre mot de passe']],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer un mot de passe',
                    ]),
                    new Length(
                        min: 6,
                        minMessage: 'Votre mot de passe doit faire au moins {{ limit }} caractères',
                        max: 4096,
                    ),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => 'J\'accepte le traitement de mes données personnelles pour l\'analyse électorale.',
                'constraints' => [
                    new IsTrue([
                        'message' => 'Vous devez accepter les conditions d\'utilisation.',
                    ]),
                ],
                'attr' => ['class' => 'h-4 w-4 rounded border-gray-300 text-benin-green focus:ring-benin-green']
            ])
            ->add('agreeGeolocation', CheckboxType::class, [
                'mapped' => false,
                'label' => 'J\'accepte d\'être géolocalisé lors de mes actions (pointage, saisie).',
                'constraints' => [
                    new IsTrue([
                        'message' => 'Vous devez consentir à la géolocalisation pour garantir l\'intégrité des résultats.',
                    ]),
                ],
                'attr' => ['class' => 'h-4 w-4 rounded border-gray-300 text-benin-green focus:ring-benin-green']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
