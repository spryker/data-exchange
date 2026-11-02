<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Communication\Controller;

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
class TemplateController extends AbstractImportJobController
{
    protected const string CONTENT_TYPE_CSV = 'text/csv';

    protected const string TEMPLATE_FILENAME_PATTERN = '%s-template.csv';

    public function downloadAction(Request $request): Response
    {
        $importJobReference = $this->resolveImportJobByRequest($request)->getReferenceOrFail();

        $criteria = (new ImportJobCriteriaTransfer())
            ->setImportJobConditions(
                (new ImportJobConditionsTransfer())->addReference($importJobReference),
            );

        $exportResult = $this->getFacade()->exportData($criteria);

        if ($exportResult->getIsSuccessful() !== true) {
            throw new NotFoundHttpException(sprintf(static::ERROR_MESSAGE_IMPORT_JOB_NOT_FOUND, $importJobReference));
        }

        $type = $exportResult->getTypeOrFail();
        $columns = $exportResult->getColumns();

        $fileName = sprintf(static::TEMPLATE_FILENAME_PATTERN, $type);

        $response = new StreamedResponse(static function () use ($columns): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $columns);
            fclose($handle);
        });

        $response->headers->set('Content-Type', static::CONTENT_TYPE_CSV);
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $fileName));

        return $response;
    }
}
