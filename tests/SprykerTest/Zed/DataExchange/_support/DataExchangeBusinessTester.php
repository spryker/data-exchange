<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange;

use Codeception\Actor;
use Generated\Shared\Transfer\ImportJobRunFileInfoTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Generated\Shared\Transfer\ImportJobTransfer;
use Spryker\Service\FileSystem\FileSystemDependencyProvider;
use Spryker\Service\Flysystem\FlysystemDependencyProvider;
use Spryker\Service\Flysystem\Plugin\FileSystem\FileSystemReaderPlugin;
use Spryker\Service\Flysystem\Plugin\FileSystem\FileSystemStreamPlugin;
use Spryker\Service\Flysystem\Plugin\FileSystem\FileSystemWriterPlugin;
use Spryker\Service\FlysystemLocalFileSystem\Plugin\Flysystem\LocalFilesystemBuilderPlugin;
use Spryker\Shared\FileSystem\FileSystemConstants;
use SprykerTest\Zed\DataExchange\Plugin\DemoCsvImportSchemaPlugin;

/**
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = null)
 * @method \Spryker\Zed\DataExchange\Business\DataExchangeFacadeInterface getFacade(?string $moduleName = null)
 *
 * @SuppressWarnings(\SprykerTest\Zed\DataExchange\PHPMD)
 */
class DataExchangeBusinessTester extends Actor
{
    use _generated\DataExchangeBusinessTesterActions;

    public const string FILE_SYSTEM_NAME_IMPORTS = 'data-exchange-imports';

    public const string FILE_SYSTEM_NAME_EXPORTS = 'data-exchange-exports';

    public const string CSV_STORED_PATH = 'demo-csv/import.csv';

    /**
     * Points both engine file systems at a fresh local directory and returns its absolute path.
     * Module tests resolve the core `FileSystemDependencyProvider`, whose plugin getters throw, so the Flysystem plugins
     * the project wires in Pyz are set here explicitly.
     */
    public function configureLocalFileSystems(): string
    {
        $this->setDependency(FileSystemDependencyProvider::PLUGIN_READER, new FileSystemReaderPlugin());
        $this->setDependency(FileSystemDependencyProvider::PLUGIN_WRITER, new FileSystemWriterPlugin());
        $this->setDependency(FileSystemDependencyProvider::PLUGIN_STREAM, new FileSystemStreamPlugin());
        $this->setDependency(FlysystemDependencyProvider::PLUGIN_COLLECTION_FILESYSTEM_BUILDER, [new LocalFilesystemBuilderPlugin()]);

        $rootDirectory = sprintf('%s/data-exchange-test-%s', sys_get_temp_dir(), uniqid());
        $this->ensureDirectoryExists($rootDirectory);

        $localFileSystemConfiguration = [
            'sprykerAdapterClass' => LocalFilesystemBuilderPlugin::class,
            'root' => $rootDirectory,
            'path' => '/',
        ];

        $this->setConfig(FileSystemConstants::FILESYSTEM_SERVICE, [
            static::FILE_SYSTEM_NAME_IMPORTS => $localFileSystemConfiguration,
            static::FILE_SYSTEM_NAME_EXPORTS => $localFileSystemConfiguration,
        ]);

        return $rootDirectory;
    }

    /**
     * Writes a CSV file into the local import file system and returns the file info a job run needs to read it.
     *
     * @param array<int, array<int, string>> $rows The header row first, then the data rows.
     */
    public function haveImportCsvFile(string $rootDirectory, string $storedPath, array $rows): ImportJobRunFileInfoTransfer
    {
        $absolutePath = sprintf('%s/%s', $rootDirectory, $storedPath);
        $this->ensureDirectoryExists(dirname($absolutePath));

        $handle = fopen($absolutePath, 'wb');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return (new ImportJobRunFileInfoTransfer())
            ->setFileSystemName(static::FILE_SYSTEM_NAME_IMPORTS)
            ->setStoredPath($storedPath)
            ->setOriginalFileName(basename($storedPath))
            ->setContentType('text/csv')
            ->setSize((int)filesize($absolutePath));
    }

    /**
     * @param array<int, array<int, string>> $rows
     * @param array<string, mixed> $importJobRunSeedData
     */
    public function havePendingImportJobRunWithCsvFile(
        string $rootDirectory,
        array $rows,
        string $storedPath = self::CSV_STORED_PATH,
        array $importJobRunSeedData = [],
    ): ImportJobRunTransfer {
        $importJobRunFileInfoTransfer = $this->haveImportCsvFile($rootDirectory, $storedPath, $rows);
        $importJobTransfer = $this->haveImportJob([
            ImportJobTransfer::TYPE => DemoCsvImportSchemaPlugin::TYPE,
            ImportJobTransfer::DEFINITION => (new DemoCsvImportSchemaPlugin())->getSchema(),
        ]);

        return $this->haveImportJobRun($importJobRunSeedData + [
            ImportJobRunTransfer::FK_IMPORT_JOB => $importJobTransfer->getIdImportJobOrFail(),
            ImportJobRunTransfer::FILE_INFO => $importJobRunFileInfoTransfer->toArray(true, true),
        ]);
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function readCsvRows(string $rootDirectory, string $storedPath): array
    {
        $handle = fopen(sprintf('%s/%s', $rootDirectory, $storedPath), 'rb');
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    protected function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        mkdir($directory, 0777, true);
    }
}
