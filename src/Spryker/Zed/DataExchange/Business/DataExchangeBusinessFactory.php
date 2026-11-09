<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business;

use Spryker\Service\FileSystem\FileSystemServiceInterface;
use Spryker\Zed\DataExchange\Business\Export\ColumnResolver\ExportColumnResolver;
use Spryker\Zed\DataExchange\Business\Export\ColumnResolver\ExportColumnResolverInterface;
use Spryker\Zed\DataExchange\Business\Export\Exporter\Exporter;
use Spryker\Zed\DataExchange\Business\Export\Exporter\ExporterInterface;
use Spryker\Zed\DataExchange\Business\Export\Writer\ExportFileWriter;
use Spryker\Zed\DataExchange\Business\Export\Writer\ExportFileWriterInterface;
use Spryker\Zed\DataExchange\Business\ImportJob\Writer\ImportJobWriter;
use Spryker\Zed\DataExchange\Business\ImportJob\Writer\ImportJobWriterInterface;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Claimer\ImportJobRunClaimer;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Claimer\ImportJobRunClaimerInterface;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Importer\ImportJobRunImporter;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Importer\ImportJobRunImporterInterface;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Manager\ImportJobRunManager;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Manager\ImportJobRunManagerInterface;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Mapper\CsvHeaderMapper;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Mapper\CsvHeaderMapperInterface;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Reader\ImportJobRunReader;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Reader\ImportJobRunReaderInterface;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Resolver\ImportJobRunClaimSettingsResolver;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Resolver\ImportJobRunClaimSettingsResolverInterface;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Writer\ExhaustedImportJobRunWriter;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Writer\ExhaustedImportJobRunWriterInterface;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Writer\ImportFileWriter;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Writer\ImportFileWriterInterface;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Writer\ImportJobRunWriter;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Writer\ImportJobRunWriterInterface;
use Spryker\Zed\DataExchange\Business\SchemaPlugin\SchemaPluginResolver;
use Spryker\Zed\DataExchange\Business\SchemaPlugin\SchemaPluginResolverInterface;
use Spryker\Zed\DataExchange\DataExchangeDependencyProvider;
use Spryker\Zed\Event\Business\EventFacadeInterface;
use Spryker\Zed\Kernel\Business\AbstractBusinessFactory;

/**
 * @method \Spryker\Zed\DataExchange\DataExchangeConfig getConfig()
 * @method \Spryker\Zed\DataExchange\Persistence\DataExchangeRepositoryInterface getRepository()
 * @method \Spryker\Zed\DataExchange\Persistence\DataExchangeEntityManagerInterface getEntityManager()
 */
class DataExchangeBusinessFactory extends AbstractBusinessFactory
{
    public function createImportJobRunManager(): ImportJobRunManagerInterface
    {
        return new ImportJobRunManager(
            $this->createImportJobRunClaimer(),
            $this->createExhaustedImportJobRunWriter(),
            $this->getEntityManager(),
            $this->createImportJobRunImporter(),
            $this->getConfig(),
            $this->getImportPreProcessorPlugins(),
            $this->getImportPostProcessorPlugins(),
        );
    }

    public function createImportJobRunReader(): ImportJobRunReaderInterface
    {
        return new ImportJobRunReader($this->getRepository());
    }

    public function createImportJobRunClaimer(): ImportJobRunClaimerInterface
    {
        return new ImportJobRunClaimer(
            $this->getRepository(),
            $this->getEntityManager(),
            $this->getConfig(),
            $this->createImportJobRunClaimSettingsResolver(),
        );
    }

    public function createExhaustedImportJobRunWriter(): ExhaustedImportJobRunWriterInterface
    {
        return new ExhaustedImportJobRunWriter(
            $this->getRepository(),
            $this->getEntityManager(),
            $this->getConfig(),
            $this->createImportJobRunClaimSettingsResolver(),
        );
    }

    public function createImportJobRunClaimSettingsResolver(): ImportJobRunClaimSettingsResolverInterface
    {
        return new ImportJobRunClaimSettingsResolver($this->getConfig());
    }

    public function createImportJobRunImporter(): ImportJobRunImporterInterface
    {
        return new ImportJobRunImporter(
            $this->getRepository(),
            $this->getEntityManager(),
            $this->getFileSystemService(),
            $this->getEventFacade(),
            $this->getConfig(),
            $this->createCsvHeaderMapper(),
            $this->createSchemaPluginResolver(),
            $this->createImportJobRunClaimSettingsResolver(),
        );
    }

    public function createCsvHeaderMapper(): CsvHeaderMapperInterface
    {
        return new CsvHeaderMapper();
    }

    public function createImportJobRunWriter(): ImportJobRunWriterInterface
    {
        return new ImportJobRunWriter(
            $this->createImportFileWriter(),
            $this->getEntityManager(),
            $this->getRepository(),
            $this->getConfig(),
        );
    }

    public function createImportFileWriter(): ImportFileWriterInterface
    {
        return new ImportFileWriter(
            $this->getFileSystemService(),
            $this->getConfig(),
        );
    }

    public function createImportJobWriter(): ImportJobWriterInterface
    {
        return new ImportJobWriter(
            $this->getEntityManager(),
            $this->createSchemaPluginResolver(),
        );
    }

    public function createExportColumnResolver(): ExportColumnResolverInterface
    {
        return new ExportColumnResolver();
    }

    public function createExporter(): ExporterInterface
    {
        return new Exporter(
            $this->getRepository(),
            $this->createExportColumnResolver(),
            $this->createSchemaPluginResolver(),
            $this->getConfig(),
            $this->createExportFileWriter(),
        );
    }

    public function createSchemaPluginResolver(): SchemaPluginResolverInterface
    {
        return new SchemaPluginResolver(
            $this->getImportSchemaPlugins(),
            $this->getExportSchemaPlugins(),
        );
    }

    public function createExportFileWriter(): ExportFileWriterInterface
    {
        return new ExportFileWriter(
            $this->getFileSystemService(),
            $this->getConfig(),
        );
    }

    /**
     * @return array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportSchemaPluginInterface>
     */
    public function getImportSchemaPlugins(): array
    {
        return $this->getProvidedDependency(DataExchangeDependencyProvider::PLUGINS_IMPORT_SCHEMA);
    }

    /**
     * @return array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ExportSchemaPluginInterface>
     */
    public function getExportSchemaPlugins(): array
    {
        return $this->getProvidedDependency(DataExchangeDependencyProvider::PLUGINS_EXPORT_SCHEMA);
    }

    /**
     * @return array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportPreProcessorPluginInterface>
     */
    public function getImportPreProcessorPlugins(): array
    {
        return $this->getProvidedDependency(DataExchangeDependencyProvider::PLUGINS_IMPORT_PRE_PROCESSOR);
    }

    /**
     * @return array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportPostProcessorPluginInterface>
     */
    public function getImportPostProcessorPlugins(): array
    {
        return $this->getProvidedDependency(DataExchangeDependencyProvider::PLUGINS_IMPORT_POST_PROCESSOR);
    }

    public function getFileSystemService(): FileSystemServiceInterface
    {
        return $this->getProvidedDependency(DataExchangeDependencyProvider::SERVICE_FILE_SYSTEM);
    }

    public function getEventFacade(): EventFacadeInterface
    {
        return $this->getProvidedDependency(DataExchangeDependencyProvider::FACADE_EVENT);
    }
}
