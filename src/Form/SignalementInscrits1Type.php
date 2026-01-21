<?php

namespace App\Form;

use App\Entity\CentreDeVote;
use App\Entity\SignalementInscrits;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SignalementInscrits1Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombreInscritsTotal')
            ->add('repartitionBureaux')
            ->add('preuveImageName')
            ->add('statut')
            ->add('createdAt', null, [
                'widget' => 'single_text',
            ])
            ->add('updatedAt', null, [
                'widget' => 'single_text',
            ])
            ->add('auteurNom')
            ->add('centreDeVote', EntityType::class, [
                'class' => CentreDeVote::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SignalementInscrits::class,
        ]);
    }
}
