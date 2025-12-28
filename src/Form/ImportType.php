<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class ImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Fichier Import (CSV, XLSX, ODS)',
                'help' => 'Colonne 1: Nom, Colonne 2: Code, Colonne 3: Villes (optionnel).',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank(),
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.oasis.opendocument.spreadsheet',
                            'application/zip', // Souvent détecté pour .ods/.xlsx
                            'application/octet-stream', // Parfois détecté si pas d'info
                            'application/x-zip-compressed',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader un fichier valide (CSV, XLSX, ODS)',
                    ])
                ],
                'attr' => [
                    'accept' => '.csv, .xlsx, .ods',
                    'class' => 'form-control'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
