<?php

namespace App\Form;

use App\Entity\Parti;
use App\Entity\Resultat;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class ResultatsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('parti', EntityType::class, [
                'class' => Parti::class,
                'choice_label' => function (Parti $parti) {
                    return $parti->getNom() . ' (' . $parti->getSigle() . ')';
                },
                'label' => 'Parti Politique',
                'placeholder' => 'Choisir un parti',
                'attr' => ['class' => 'form-select mb-3']
            ])
            ->add('nombreVoix', IntegerType::class, [
                'label' => 'Nombre de voix',
                'attr' => ['class' => 'form-control mb-3', 'min' => 0],
                'constraints' => [
                    new PositiveOrZero(['message' => 'Le nombre de voix ne peut pas être négatif.'])
                ]
            ])
            ->add('pvImageFile', FileType::class, [
                'label' => 'Photo du Procès-Verbal (PV)',
                'mapped' => true, // C'est mappé via VichUploader
                'required' => true,
                'attr' => ['class' => 'form-control mb-3'],
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'application/pdf',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPG, PNG, WEBP) ou un PDF.',
                    ])
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer le Résultat',
                'attr' => ['class' => 'btn btn-primary w-100']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Resultat::class,
        ]);
    }
}
