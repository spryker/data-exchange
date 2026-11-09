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
use Spryker\Zed\DataExchange\Business\ImportJobRun\Claimer\ImportJobRunClaimerInterface;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Importer\ImportJobRunImporterInterface;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Writer\ExhaustedImportJobRunWriterInterface;
use Spryker\Zed\DataExchange\DataExchangeConfig;
use Spryker\Zed\DataExchange\Persistence\DataExchangeEntityManagerInterface;
use Spryker\Zed\EventBehavior\EventBehaviorConfig;
use Throwable;

class ImportJobRunManager implements ImportJobRunManagerInterface
{
    use LoggerTrait;

    protected const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * @param array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportPreProcessorPluginInterface> $preProcessorPlugins
     * @param array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportPostProcessorPluginInterface> $postProcessorPlugins
     */
    public function __construct(
        protected ImportJobRunClaimerInterface $importJobRunClaimer,
        protected ExhaustedImportJobRunWriterInterface $exhaustedImportJobRunWriter,
        protected DataExchangeEntityManagerInterface $entityManager,
        protected ImportJobRunImporterInterface $importer,
        protected DataExchangeConfig $config,
        protected array $preProcessorPlugins,
        protected array $postProcessorPlugins,
    ) {
    }

    public function processNextPendingRun(): bool
    {
        $this->exhaustedImportJobRunWriter->failExhaustedImportJobRuns();

        $importJobRun = $this->importJobRunClaimer->claimNextImportJobRun();

        if ($importJobRun === null) {
            return false;
        }

        try {
            $this->executePreProcessors($importJobRun);

            if (!$this->importer->importJobRun($importJobRun)) {
                return true;
            }
        } catch (Throwable $exception) {
            EventBehaviorConfig::enableEvent();
            $this->getLogger()->error(
                sprintf('Import job run #%d failed: %s', $importJobRun->getIdImportJobRun(), $exception->getMessage()),
                ['exception' => $exception],
            );
            $this->markAsFailed($importJobRun, $exception);

            return true;
        }

        $this->executePostProcessors($importJobRun);

        return true;
    }

    protected function markAsFailed(ImportJobRunTransfer $importJobRun, Throwable $exception): void
    {
        $finishedAt = (new DateTime())->format(static::DATETIME_FORMAT);

        $importJobRun
            ->setStatus($this->config->getImportStatusFailed())
            ->setImportFinishedAt($finishedAt)
            ->setUpdatedAt($finishedAt);

        if (!$this->entityManager->updateClaimedImportJobRun($importJobRun)) {
            return;
        }

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
        try {
            foreach ($this->postProcessorPlugins as $plugin) {
                $plugin->postProcess($importJobRun);
            }
        } catch (Throwable $exception) {
            $this->getLogger()->error(
                sprintf('Post-processing of import job run #%d failed: %s', $importJobRun->getIdImportJobRun(), $exception->getMessage()),
                ['exception' => $exception],
            );
            $this->entityManager->createImportJobRunError(
                (new ImportJobRunErrorTransfer())
                    ->setFkImportJobRun($importJobRun->getIdImportJobRunOrFail())
                    ->setErrorMessage(sprintf('Unhandled exception during post-processing: %s', $exception->getMessage())),
            );
        }
    }
}
