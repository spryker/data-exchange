<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Persistence\Propel\Mapper;

use Generated\Shared\Transfer\ImportJobRunErrorTransfer;
use Generated\Shared\Transfer\ImportJobRunFileInfoTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Generated\Shared\Transfer\ImportJobTransfer;
use Orm\Zed\DataExchange\Persistence\SpyImportJob;
use Orm\Zed\DataExchange\Persistence\SpyImportJobRun;
use Orm\Zed\DataExchange\Persistence\SpyImportJobRunError;
use Propel\Runtime\Collection\ObjectCollection;
use Spryker\Service\UtilEncoding\UtilEncodingServiceInterface;

class DataExchangeMapper
{
    protected const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        protected UtilEncodingServiceInterface $utilEncodingService,
    ) {
    }

    /**
     * @return array<\Generated\Shared\Transfer\ImportJobTransfer>
     */
    public function mapImportJobEntitiesToTransfers(ObjectCollection $importJobEntities): array
    {
        $transfers = [];

        foreach ($importJobEntities as $entity) {
            $transfers[] = $this->mapImportJobEntityToTransfer($entity, new ImportJobTransfer());
        }

        return $transfers;
    }

    public function mapImportJobEntityToTransfer(SpyImportJob $entity, ImportJobTransfer $transfer): ImportJobTransfer
    {
        $transfer->fromArray($entity->toArray(), true);
        $transfer->setDefinition((array)($this->utilEncodingService->decodeJson($entity->getDefinition(), true) ?? []));

        return $transfer;
    }

    public function mapImportJobTransferToEntity(ImportJobTransfer $importJobTransfer, SpyImportJob $entity): SpyImportJob
    {
        $data = $importJobTransfer->modifiedToArrayNotRecursiveCamelCased();
        unset($data['definition']);
        $entity->fromArray($data);
        $entity->setDefinition((string)$this->utilEncodingService->encodeJson($importJobTransfer->getDefinition()));

        return $entity;
    }

    public function mapImportJobRunEntityToTransfer(SpyImportJobRun $entity, ImportJobRunTransfer $transfer): ImportJobRunTransfer
    {
        $transfer
            ->setIdImportJobRun($entity->getIdImportJobRun())
            ->setFkImportJob($entity->getFkImportJob())
            ->setStatus($entity->getStatus())
            ->setNumberOfProcessedLines($entity->getNumberOfProcessedLines())
            ->setNumberOfSuccessfullyProcessedLines($entity->getNumberOfSuccessfullyProcessedLines())
            ->setNumberOfFailedLines($entity->getNumberOfFailedLines())
            ->setNumberOfProcessedBytes($entity->getNumberOfProcessedBytes() !== null ? (int)$entity->getNumberOfProcessedBytes() : null)
            ->setImportStartedAt($entity->getImportStartedAt() ? $entity->getImportStartedAt()->format(static::DATETIME_FORMAT) : null)
            ->setImportFinishedAt($entity->getImportFinishedAt() ? $entity->getImportFinishedAt()->format(static::DATETIME_FORMAT) : null)
            ->setClaimedBy($entity->getClaimedBy())
            ->setLeaseExpiresAt($entity->getLeaseExpiresAt() ? $entity->getLeaseExpiresAt()->format(static::DATETIME_FORMAT) : null)
            ->setAttemptCount($entity->getAttemptCount())
            ->setCreatedAt($entity->getCreatedAt() ? $entity->getCreatedAt()->format(static::DATETIME_FORMAT) : null)
            ->setUpdatedAt($entity->getUpdatedAt() ? $entity->getUpdatedAt()->format(static::DATETIME_FORMAT) : null);

        $fileInfoRaw = $entity->getFileInfo();

        if ($fileInfoRaw) {
            $fileInfoData = (array)($this->utilEncodingService->decodeJson($fileInfoRaw, true) ?? []);
            $transfer->setFileInfo(
                (new ImportJobRunFileInfoTransfer())->fromArray($fileInfoData, true),
            );
        }

        return $transfer;
    }

    public function mapImportJobRunTransferToEntity(ImportJobRunTransfer $transfer, SpyImportJobRun $entity): SpyImportJobRun
    {
        $entity->setFkImportJob($transfer->getFkImportJobOrFail());

        if ($transfer->getStatus() !== null) {
            $entity->setStatus($transfer->getStatus());
        }

        if ($transfer->getNumberOfProcessedLines() !== null) {
            $entity->setNumberOfProcessedLines($transfer->getNumberOfProcessedLines());
        }

        if ($transfer->getNumberOfSuccessfullyProcessedLines() !== null) {
            $entity->setNumberOfSuccessfullyProcessedLines($transfer->getNumberOfSuccessfullyProcessedLines());
        }

        if ($transfer->getNumberOfFailedLines() !== null) {
            $entity->setNumberOfFailedLines($transfer->getNumberOfFailedLines());
        }

        if ($transfer->getNumberOfProcessedBytes() !== null) {
            $entity->setNumberOfProcessedBytes((string)$transfer->getNumberOfProcessedBytes());
        }

        if ($transfer->getImportStartedAt() !== null) {
            $entity->setImportStartedAt($transfer->getImportStartedAt());
        }

        if ($transfer->getImportFinishedAt() !== null) {
            $entity->setImportFinishedAt($transfer->getImportFinishedAt());
        }

        if ($transfer->getFileInfo() !== null) {
            $entity->setFileInfo($this->mapImportJobRunFileInfoTransferToJson($transfer->getFileInfo()));
        }

        return $entity;
    }

    public function mapImportJobRunFileInfoTransferToJson(ImportJobRunFileInfoTransfer $importJobRunFileInfoTransfer): string
    {
        return (string)$this->utilEncodingService->encodeJson($importJobRunFileInfoTransfer->toArray());
    }

    public function mapImportJobRunErrorEntityToTransfer(SpyImportJobRunError $entity, ImportJobRunErrorTransfer $transfer): ImportJobRunErrorTransfer
    {
        return $transfer
            ->setIdImportJobRunError($entity->getIdImportJobRunError())
            ->setFkImportJobRun($entity->getFkImportJobRun())
            ->setCsvRowNumber($entity->getCsvRowNumber())
            ->setErrorMessage($entity->getErrorMessage());
    }
}
