<?php

namespace App\Form;

use App\Entity\Participation;
use App\Validator\IncreasingVotersCount;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

class ParticipationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombreVotants', IntegerType::class, [
                'label' => 'Nombre de votants à cet instant',
                'attr' => [
                    'class' => 'w-full px-6 py-4 rounded-2xl border-2 border-slate-100 focus:border-benin-green outline-none text-2xl font-black text-center',
                    'inputmode' => 'numeric',
                    'placeholder' => '0'
                ],
                'constraints' => [
                    new NotBlank(),
                    new GreaterThanOrEqual(0),
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Participation::class,
            'allow_extra_fields' => true,
        ]);
    }
}
