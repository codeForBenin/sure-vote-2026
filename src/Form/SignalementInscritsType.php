<?php

namespace App\Form;

use App\Entity\CentreDeVote;
use App\Entity\SignalementInscrits;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class SignalementInscritsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('auteurNom', TextType::class, [
                'label' => 'Votre Nom (Assesseur ou Citoyen)',
                'attr' => ['placeholder' => 'Ex: Jean Dupont'],
                'constraints' => [new NotBlank()]
            ])
            ->add('centreId', HiddenType::class, [
                'mapped' => false,
                'attr' => ['data-signalement-target' => 'centreIdInput']
            ])
            ->add('nombreInscritsTotal', IntegerType::class, [
                'label' => 'Nombre TOTAL d\'inscrits affichés pour tout le centre',
                'attr' => ['min' => 0]
            ])
            ->add('preuveImageFile', FileType::class, [
                'label' => 'Photo de la liste / Preuve (Obligatoire)',
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Merci d\'uploader une image valide (JPG, PNG, WEBP)',
                    ])
                ],
            ])
        ;

        // Note: Le champ "repartitionBureaux" (JSON) sera géré "à la main" 
        // ou via un CollectionType mappé différemment car c'est du JSON et non une relation OneToMany standard.
        // Pour simplifier l'UX "Réactive" demandée, on va souvent laisser le contrôleur/JS gérer la structure JSON,
        // ou utiliser un champ caché que le JS remplit.
        // Ici, je vais ajouter un champ TextType caché pour stocker le JSON généré par le frontend.

        $builder->add('repartitionBureauxJson', TextType::class, [
            'mapped' => false, // Non mappé directement, on le traitera dans le contrôleur
            'attr' => ['class' => 'hidden-input-bureaux'],
            'required' => false
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SignalementInscrits::class,
        ]);
    }
}
