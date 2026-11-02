<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Mapper;

class CsvHeaderMapper implements CsvHeaderMapperInterface
{
    public const string ROW_KEY_PROPERTY_NAMES_IN_FILE = '_property_names_in_file';

    /**
     * @var array<string, string> Exact name in file → system name from schema.
     */
    protected array $exactRules = [];

    /**
     * @var array<int, array{regex: string, placeholders: array<string>, systemTemplate: string}>
     */
    protected array $patternRules = [];

    /**
     * @var array<string, string> file header → system name, resolved from actual CSV headers.
     */
    protected array $forwardMap = [];

    /**
     * @var array<string, string> system name → file header.
     */
    protected array $reverseMap = [];

    /**
     * {@inheritDoc}
     */
    public function buildMappingRulesFromSchema(array $schema): void
    {
        $this->exactRules = [];
        $this->patternRules = [];

        foreach ($schema as $entry) {
            $nameInTheFile = $entry['property_name_in_file'];
            $systemName = $entry['system_property_name'];

            if (!str_contains($nameInTheFile, '{')) {
                $this->exactRules[$nameInTheFile] = $systemName;

                continue;
            }

            preg_match_all('/\{(\w+)\}/', $nameInTheFile, $matches);
            $regex = preg_replace('/\\\{(\w+)\\\}/', '(.+)', preg_quote($nameInTheFile, '/'));

            $this->patternRules[] = [
                'regex' => sprintf('/^%s$/', $regex),
                'placeholders' => $matches[1],
                'systemTemplate' => $systemName,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function mapHeaders(array $fileHeaders): void
    {
        $this->forwardMap = [];
        $this->reverseMap = [];

        foreach ($fileHeaders as $header) {
            $systemName = $this->resolveSystemName($header);

            if ($systemName === null) {
                continue;
            }

            $this->forwardMap[$header] = $systemName;
            $this->reverseMap[$systemName] = $header;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function mapRow(array $row): array
    {
        $valueIndexedBySystemProperty = [];

        foreach ($row as $fileHeader => $value) {
            if (!isset($this->forwardMap[$fileHeader])) {
                continue;
            }

            $valueIndexedBySystemProperty[$this->forwardMap[$fileHeader]] = $value;
        }

        return $valueIndexedBySystemProperty;
    }

    /**
     * @return array<string, string>
     */
    public function getReverseMap(): array
    {
        return $this->reverseMap;
    }

    protected function resolveSystemName(string $fileHeaderName): ?string
    {
        if (isset($this->exactRules[$fileHeaderName])) {
            return $this->exactRules[$fileHeaderName];
        }

        foreach ($this->patternRules as $patternRule) {
            if (!preg_match($patternRule['regex'], $fileHeaderName, $capturedValues)) {
                continue;
            }

            array_shift($capturedValues);
            $placeholderReplacements = [];

            foreach ($patternRule['placeholders'] as $index => $placeholderName) {
                $normalizedValue = strtolower(str_replace('-', '_', $capturedValues[$index]));
                $placeholderReplacements[sprintf('{%s}', $placeholderName)] = $normalizedValue;
            }

            return strtr($patternRule['systemTemplate'], $placeholderReplacements);
        }

        return null;
    }
}
