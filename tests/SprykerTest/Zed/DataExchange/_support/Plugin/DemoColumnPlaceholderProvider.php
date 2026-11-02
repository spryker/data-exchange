<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Plugin;

use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ColumnPlaceholderProviderInterface;

class DemoColumnPlaceholderProvider implements ColumnPlaceholderProviderInterface
{
    /**
     * @param array<string, array<string>> $placeholderValues
     */
    public function __construct(protected array $placeholderValues = ['locale' => ['de_DE', 'en_US']])
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getPlaceholderValues(): array
    {
        return $this->placeholderValues;
    }
}
