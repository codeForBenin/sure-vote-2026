<?php

namespace App\Form;

use App\Entity\Observation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

class ObservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('contenu', TextareaType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Décrivez votre observation ou incident ici...',
                    'rows' => 4,
                    'class' => 'w-full rounded-xl border-slate-200 bg-slate-50 focus:border-benin-green focus:ring-benin-green p-4'
                ]
            ])
            ->add('niveau', ChoiceType::class, [
                'choices' => [
                    'Information' => 'INFO',
                    'Alerte / Urgent' => 'URGENT',
                ],
                'expanded' => true,
                'multiple' => false,
                'label_attr' => ['class' => 'text-sm font-bold text-slate-700 mb-2 block'],
                'attr' => ['class' => 'flex flex-col sm:flex-row gap-4 items-center'],
            ])
            ->add('imageFile', VichImageType::class, [
                'required' => false,
                'allow_delete' => false,
                'download_uri' => false,
                'image_uri' => false, // On ne montre pas l'image avant l'upload
                'label' => 'Ajouter une photo (Preuve)',
                'attr' => [
                    'class' => 'file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100',
                    'accept' => 'image/*'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Observation::class,
        ]);
    }
}
