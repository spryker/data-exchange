<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Communication\Form\DataProvider;

use Spryker\Zed\DataExchange\Communication\Form\ImportJobForm;

class ImportJobFormDataProvider
{
    /**
     * @param array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportSchemaPluginInterface> $schemaPlugins
     */
    public function __construct(protected array $schemaPlugins)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return [
            ImportJobForm::OPTION_TYPE_CHOICES => $this->prepareTypeChoices(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function prepareTypeChoices(): array
    {
        $choices = [];

        foreach ($this->schemaPlugins as $schemaPlugin) {
            $type = $schemaPlugin->getType();
            $choices[$type] = $type;
        }

        return $choices;
    }
}
