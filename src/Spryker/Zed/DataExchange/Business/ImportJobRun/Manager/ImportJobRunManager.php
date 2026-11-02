<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Manager;

use DateTime;
use Generated\Shared\Transfer\ImportJobRunErrorTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Spryker\Shared\Log\LoggerTrait;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Importer\ImportJobRunImporterInterface;
use Spryker\Zed\DataExchange\DataExchangeConfig;
use Spryker\Zed\DataExchange\Persistence\DataExchangeEntityManagerInterface;
use Spryker\Zed\DataExchange\Persistence\DataExchangeRepositoryInterface;
use Spryker\Zed\EventBehavior\EventBehaviorConfig;
use Throwable;

class ImportJobRunManager implements ImportJobRunManagerInterface
{
    use LoggerTrait;

    /**
     * @param array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportPreProcessorPluginInterface> $preProcessorPlugins
     * @param array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportPostProcessorPluginInterface> $postProcessorPlugins
     */
    public function __construct(
        protected DataExchangeRepositoryInterface $repository,
        protected DataExchangeEntityManagerInterface $entityManager,
        protected ImportJobRunImporterInterface $importer,
        protected DataExchangeConfig $config,
        protected array $preProcessorPlugins,
        protected array $postProcessorPlugins,
    ) {
    }

    public function processNextPendingRun(): void
    {
        $importJobRun = $this->repository->findOldestPendingJobRun();

        if ($importJobRun === null) {
            return;
        }

        $this->markAsProcessing($importJobRun);

        try {
            $this->executePreProcessors($importJobRun);
            $this->importer->importJobRun($importJobRun);
            $this->executePostProcessors($importJobRun);
        } catch (Throwable $exception) {
            EventBehaviorConfig::enableEvent();
            $this->getLogger()->error(
                sprintf('Import job run #%d failed: %s', $importJobRun->getIdImportJobRun(), $exception->getMessage()),
                ['exception' => $exception],
            );
            $this->markAsFailed($importJobRun, $exception);
        }
    }

    protected function markAsProcessing(ImportJobRunTransfer $importJobRun): void
    {
        $importJobRun
            ->setStatus($this->config->getImportStatusProcessing())
            ->setImportStartedAt((new DateTime())->format('Y-m-d H:i:s'));

        $this->entityManager->updateImportJobRun($importJobRun);
    }

    protected function markAsFailed(ImportJobRunTransfer $importJobRun, Throwable $exception): void
    {
        $importJobRun
            ->setStatus($this->config->getImportStatusFailed())
            ->setImportFinishedAt((new DateTime())->format('Y-m-d H:i:s'));

        $this->entityManager->updateImportJobRun($importJobRun);

        $this->entityManager->createImportJobRunError(
            (new ImportJobRunErrorTransfer())
                ->setFkImportJobRun($importJobRun->getIdImportJobRunOrFail())
                ->setErrorMessage(sprintf('Unhandled exception during import: %s', $exception->getMessage())),
        );
    }

    protected function executePreProcessors(ImportJobRunTransfer $importJobRun): void
    {
        foreach ($this->preProcessorPlugins as $plugin) {
            $plugin->preProcess($importJobRun);
        }
    }

    protected function executePostProcessors(ImportJobRunTransfer $importJobRun): void
    {
        foreach ($this->postProcessorPlugins as $plugin) {
            $plugin->postProcess($importJobRun);
        }
    }
}
