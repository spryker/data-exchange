<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Communication;

use Orm\Zed\DataExchange\Persistence\SpyImportJobQuery;
use Spryker\Service\FileSystem\FileSystemServiceInterface;
use Spryker\Service\UtilDateTime\UtilDateTimeServiceInterface;
use Spryker\Zed\DataExchange\Communication\Form\DataProvider\ImportJobFormDataProvider;
use Spryker\Zed\DataExchange\Communication\Form\DataProvider\ImportJobRunFormDataProvider;
use Spryker\Zed\DataExchange\Communication\Form\ImportJobForm;
use Spryker\Zed\DataExchange\Communication\Form\ImportJobRunForm;
use Spryker\Zed\DataExchange\Communication\Reader\ImportJobReader;
use Spryker\Zed\DataExchange\Communication\Reader\ImportJobReaderInterface;
use Spryker\Zed\DataExchange\Communication\Table\ImportJobRunTable;
use Spryker\Zed\DataExchange\Communication\Table\ImportJobTable;
use Spryker\Zed\DataExchange\DataExchangeDependencyProvider;
use Spryker\Zed\Kernel\Communication\AbstractCommunicationFactory;
use Symfony\Component\Form\FormInterface;

/**
 * @method \Spryker\Zed\DataExchange\Business\DataExchangeFacadeInterface getFacade()
 * @method \Spryker\Zed\DataExchange\DataExchangeConfig getConfig()
 */
class DataExchangeCommunicationFactory extends AbstractCommunicationFactory
{
    public function createImportJobTable(): ImportJobTable
    {
        return new ImportJobTable(SpyImportJobQuery::create(), $this->getConfig());
    }

    public function createImportJobRunTable(int $idImportJob): ImportJobRunTable
    {
        return new ImportJobRunTable($idImportJob, $this->getUtilDateTimeService());
    }

    public function createImportJobForm(): FormInterface
    {
        return $this->getFormFactory()->create(
            ImportJobForm::class,
            null,
            $this->createImportJobFormDataProvider()->getOptions(),
        );
    }

    public function createImportJobFormDataProvider(): ImportJobFormDataProvider
    {
        return new ImportJobFormDataProvider(
            $this->getImportSchemaPlugins(),
        );
    }

    public function createImportJobRunForm(): FormInterface
    {
        return $this->getFormFactory()->create(
            ImportJobRunForm::class,
            null,
            $this->createImportJobRunFormDataProvider()->getOptions(),
        );
    }

    public function createImportJobRunFormDataProvider(): ImportJobRunFormDataProvider
    {
        return new ImportJobRunFormDataProvider(
            $this->getConfig(),
        );
    }

    public function createImportJobReader(): ImportJobReaderInterface
    {
        return new ImportJobReader($this->getFacade());
    }

    /**
     * @return array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportSchemaPluginInterface>
     */
    public function getImportSchemaPlugins(): array
    {
        return $this->getProvidedDependency(DataExchangeDependencyProvider::PLUGINS_IMPORT_SCHEMA);
    }

    public function getUtilDateTimeService(): UtilDateTimeServiceInterface
    {
        return $this->getProvidedDependency(DataExchangeDependencyProvider::SERVICE_UTIL_DATE_TIME);
    }

    public function getFileSystemService(): FileSystemServiceInterface
    {
        return $this->getProvidedDependency(DataExchangeDependencyProvider::SERVICE_FILE_SYSTEM);
    }
}
