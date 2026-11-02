<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Communication\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class ImportJobRunForm extends AbstractType
{
    public const string FIELD_FILE = 'file';

    public const string OPTION_MAX_FILE_SIZE = 'max_file_size';

    public const string OPTION_ALLOWED_MIME_TYPES = 'allowed_mime_types';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addFileField($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired([
            static::OPTION_MAX_FILE_SIZE,
            static::OPTION_ALLOWED_MIME_TYPES,
        ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function addFileField(FormBuilderInterface $builder, array $options): static
    {
        $builder->add(static::FIELD_FILE, FileType::class, [
            'label' => 'CSV File',
            'constraints' => [
                new NotBlank(),
                new File([
                    'maxSize' => $options[static::OPTION_MAX_FILE_SIZE],
                    'mimeTypes' => $options[static::OPTION_ALLOWED_MIME_TYPES],
                    'mimeTypesMessage' => 'Invalid file type. Allowed types: {{ types }}.',
                ]),
            ],
            'attr' => ['accept' => implode(',', $options[static::OPTION_ALLOWED_MIME_TYPES])],
        ]);

        return $this;
    }
}
