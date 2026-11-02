<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Mapper;

interface CsvHeaderMapperInterface
{
    /**
     * Builds exact and pattern-based mapping rules from the schema definition.
     *
     * @param array<int, array<string, mixed>> $schema
     */
    public function buildMappingRulesFromSchema(array $schema): void;

    /**
     * Resolves actual CSV file headers against schema rules.
     * Must be called once with the CSV header row before mapRow.
     *
     * @param array<string> $fileHeaders
     */
    public function mapHeaders(array $fileHeaders): void;

    /**
     * Replaces CSV file header keys with system property names.
     *
     * @param array<string, string> $row
     *
     * @return array<string, string>
     */
    public function mapRow(array $row): array;

    /**
     * Returns the reverse mapping (system name → file header name).
     *
     * @return array<string, string>
     */
    public function getReverseMap(): array;
}
