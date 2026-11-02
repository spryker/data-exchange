<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange;

use Spryker\Service\FileSystem\FileSystemServiceInterface;
use Spryker\Service\UtilDateTime\UtilDateTimeServiceInterface;
use Spryker\Service\UtilEncoding\UtilEncodingServiceInterface;
use Spryker\Zed\Event\Business\EventFacadeInterface;
use Spryker\Zed\Kernel\AbstractBundleDependencyProvider;
use Spryker\Zed\Kernel\Container;

/**
 * @method \Spryker\Zed\DataExchange\DataExchangeConfig getConfig()
 */
class DataExchangeDependencyProvider extends AbstractBundleDependencyProvider
{
    public const string SERVICE_FILE_SYSTEM = 'SERVICE_FILE_SYSTEM';

    public const string SERVICE_UTIL_DATE_TIME = 'SERVICE_UTIL_DATE_TIME';

    public const string SERVICE_UTIL_ENCODING = 'SERVICE_UTIL_ENCODING';

    public const string FACADE_EVENT = 'FACADE_EVENT';

    public const string PLUGINS_IMPORT_SCHEMA = 'PLUGINS_IMPORT_SCHEMA';

    public const string PLUGINS_EXPORT_SCHEMA = 'PLUGINS_EXPORT_SCHEMA';

    public const string PLUGINS_IMPORT_PRE_PROCESSOR = 'PLUGINS_IMPORT_PRE_PROCESSOR';

    public const string PLUGINS_IMPORT_POST_PROCESSOR = 'PLUGINS_IMPORT_POST_PROCESSOR';

    public function provideBusinessLayerDependencies(Container $container): Container
    {
        $container = parent::provideBusinessLayerDependencies($container);
        $container = $this->addFileSystemService($container);
        $container = $this->addEventFacade($container);
        $container = $this->addImportSchemaPlugins($container);
        $container = $this->addExportSchemaPlugins($container);
        $container = $this->addImportPreProcessorPlugins($container);
        $container = $this->addImportPostProcessorPlugins($container);

        return $container;
    }

    public function providePersistenceLayerDependencies(Container $container): Container
    {
        $container = parent::providePersistenceLayerDependencies($container);
        $container = $this->addUtilEncodingService($container);

        return $container;
    }

    public function provideCommunicationLayerDependencies(Container $container): Container
    {
        $container = parent::provideCommunicationLayerDependencies($container);
        $container = $this->addFileSystemService($container);
        $container = $this->addImportSchemaPlugins($container);
        $container = $this->addUtilDateTimeService($container);

        return $container;
    }

    protected function addFileSystemService(Container $container): Container
    {
        $container->set(static::SERVICE_FILE_SYSTEM, function (Container $container): FileSystemServiceInterface {
            return $container->getLocator()->fileSystem()->service();
        });

        return $container;
    }

    protected function addEventFacade(Container $container): Container
    {
        $container->set(static::FACADE_EVENT, function (Container $container): EventFacadeInterface {
            return $container->getLocator()->event()->facade();
        });

        return $container;
    }

    protected function addUtilDateTimeService(Container $container): Container
    {
        $container->set(static::SERVICE_UTIL_DATE_TIME, function (Container $container): UtilDateTimeServiceInterface {
            return $container->getLocator()->utilDateTime()->service();
        });

        return $container;
    }

    protected function addUtilEncodingService(Container $container): Container
    {
        $container->set(static::SERVICE_UTIL_ENCODING, function (Container $container): UtilEncodingServiceInterface {
            return $container->getLocator()->utilEncoding()->service();
        });

        return $container;
    }

    protected function addImportSchemaPlugins(Container $container): Container
    {
        $container->set(static::PLUGINS_IMPORT_SCHEMA, function () {
            return $this->getImportSchemaPlugins();
        });

        return $container;
    }

    /**
     * @return array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportSchemaPluginInterface>
     */
    protected function getImportSchemaPlugins(): array
    {
        return [];
    }

    protected function addExportSchemaPlugins(Container $container): Container
    {
        $container->set(static::PLUGINS_EXPORT_SCHEMA, function () {
            return $this->getExportSchemaPlugins();
        });

        return $container;
    }

    /**
     * @return array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ExportSchemaPluginInterface>
     */
    protected function getExportSchemaPlugins(): array
    {
        return [];
    }

    protected function addImportPreProcessorPlugins(Container $container): Container
    {
        $container->set(static::PLUGINS_IMPORT_PRE_PROCESSOR, function () {
            return $this->getImportPreProcessorPlugins();
        });

        return $container;
    }

    /**
     * @return array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportPreProcessorPluginInterface>
     */
    protected function getImportPreProcessorPlugins(): array
    {
        return [];
    }

    protected function addImportPostProcessorPlugins(Container $container): Container
    {
        $container->set(static::PLUGINS_IMPORT_POST_PROCESSOR, function () {
            return $this->getImportPostProcessorPlugins();
        });

        return $container;
    }

    /**
     * @return array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportPostProcessorPluginInterface>
     */
    protected function getImportPostProcessorPlugins(): array
    {
        return [];
    }
}
