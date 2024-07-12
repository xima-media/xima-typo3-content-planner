<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\StatusItem;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;

class RecordController
{
    public function filterAction(ServerRequestInterface $request): ResponseInterface
    {
        $search = $request->getQueryParams()['search'];
        $status = (int)$request->getQueryParams()['status'];
        $assignee = (int)$request->getQueryParams()['assignee'];
        $type = $request->getQueryParams()['type'];

        $records = ContentUtility::getRecordsByFilter($search, $status, $assignee, $type);
        $result = [];
        foreach ($records as $record) {
            $result[] = StatusItem::create($record)->toArray();
        }
        return new JsonResponse($result);
    }
}
