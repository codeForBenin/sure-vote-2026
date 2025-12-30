<?php

namespace App\Form;

use App\Entity\Parti;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class GlobalResultatsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $partis = $options['partis'];

        // Champ Upload PV (Unique pour tous les résultats)
        $builder->add('pvImageFile', FileType::class, [
            'label' => 'Photo du Procès-Verbal (PV)',
            'mapped' => false,
            'required' => true,
            'constraints' => [
                new NotBlank(['message' => 'Veuillez télécharger la photo du PV']),
                new File([
                    'maxSize' => '10M',
                    'mimeTypes' => [
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                    ],
                    'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPG, PNG, WEBP)',
                ])
            ],
            'attr' => [
                'accept' => 'image/*',
                'class' => 'file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100',
            ],
        ]);

        // Génération dynamique des champs pour chaque parti
        /** @var Parti $parti */
        foreach ($partis as $parti) {
            $builder->add('parti_' . $parti->getId(), IntegerType::class, [
                'label' => $parti->getNom() . ' (' . $parti->getSigle() . ')',
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new PositiveOrZero(),
                ],
                'attr' => [
                    'min' => 0,
                    'placeholder' => 'Nombre de voix',
                    'class' => 'text-center font-bold text-lg',
                    'data-parti-sigle' => $parti->getSigle(),
                    'data-parti-color' => $parti->getCouleur(),
                ],
                'row_attr' => [
                    'class' => 'p-4 border rounded-xl bg-slate-50 flex items-center justify-between gap-4 mb-4 result-row',
                    'style' => 'border-left: 5px solid ' . $parti->getCouleur()
                ]
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'partis' => [], // Liste des entités Parti passée par le controller
        ]);
    }
}
