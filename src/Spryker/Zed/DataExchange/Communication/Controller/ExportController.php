<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Communication\Controller;

use Generated\Shared\Transfer\FileSystemStreamTransfer;
use Generated\Shared\Transfer\ImportJobConditionsTransfer;
use Generated\Shared\Transfer\ImportJobCriteriaTransfer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @method \Spryker\Zed\DataExchange\Communication\DataExchangeCommunicationFactory getFactory()
 * @method \Spryker\Zed\DataExchange\Business\DataExchangeFacade getFacade()
 */
class ExportController extends AbstractImportJobController
{
    protected const string CONTENT_TYPE_CSV = 'text/csv';

    /**
     * @return array<string, mixed>
     */
    public function indexAction(): array
    {
        $importJobCollection = $this->getFacade()->getImportJobCollection(new ImportJobCriteriaTransfer());

        return $this->viewResponse([
            'importJobs' => $importJobCollection->getImportJobs(),
        ]);
    }

    public function downloadAction(Request $request): Response
    {
        $importJobReference = $this->resolveImportJobByRequest($request)->getReferenceOrFail();

        $criteria = (new ImportJobCriteriaTransfer())
            ->setImportJobConditions(
                (new ImportJobConditionsTransfer())->addReference($importJobReference),
            )
            ->setIsWithData(true);

        $exportResult = $this->getFacade()->exportData($criteria);

        if ($exportResult->getIsSuccessful() !== true) {
            throw new NotFoundHttpException(sprintf(static::ERROR_MESSAGE_IMPORT_JOB_NOT_FOUND, $importJobReference));
        }

        $fileInfo = $exportResult->getFileInfoOrFail();
        $fileSystemService = $this->getFactory()->getFileSystemService();

        $fileSystemStreamTransfer = (new FileSystemStreamTransfer())
            ->setFileSystemName($fileInfo->getFileSystemNameOrFail())
            ->setPath($fileInfo->getStoredPathOrFail());

        $stream = $fileSystemService->readStream($fileSystemStreamTransfer);

        $response = new StreamedResponse(static function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        });

        $response->headers->set('Content-Type', $fileInfo->getContentType() ?? static::CONTENT_TYPE_CSV);
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $fileInfo->getOriginalFileNameOrFail()));

        return $response;
    }
}
