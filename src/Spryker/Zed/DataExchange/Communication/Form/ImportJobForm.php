<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Communication\Form;

use Generated\Shared\Transfer\ImportJobTransfer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ImportJobForm extends AbstractType
{
    public const string FIELD_NAME = ImportJobTransfer::NAME;

    public const string FIELD_DESCRIPTION = ImportJobTransfer::DESCRIPTION;

    public const string FIELD_TYPE = ImportJobTransfer::TYPE;

    public const string OPTION_TYPE_CHOICES = 'type_choices';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addNameField($builder)
            ->addDescriptionField($builder)
            ->addTypeField($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ImportJobTransfer::class,
            static::OPTION_TYPE_CHOICES => [],
        ]);
    }

    protected function addNameField(FormBuilderInterface $builder): static
    {
        $builder->add(static::FIELD_NAME, TextType::class, [
            'label' => 'Name',
            'constraints' => [new NotBlank()],
        ]);

        return $this;
    }

    protected function addDescriptionField(FormBuilderInterface $builder): static
    {
        $builder->add(static::FIELD_DESCRIPTION, TextareaType::class, [
            'label' => 'Description',
            'required' => false,
        ]);

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function addTypeField(FormBuilderInterface $builder, array $options): static
    {
        $builder->add(static::FIELD_TYPE, ChoiceType::class, [
            'label' => 'Type',
            'choices' => $options[static::OPTION_TYPE_CHOICES],
            'constraints' => [new NotBlank()],
        ]);

        return $this;
    }
}
