<?php

namespace App\Form;

use App\Entity\Observation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Observation::class,
        ]);
    }
}
