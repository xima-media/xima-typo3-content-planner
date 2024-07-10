<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\StatusItem;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;

class PageController
{
    public function filterAction(ServerRequestInterface $request): ResponseInterface
    {
        $search = $request->getQueryParams()['search'];
        $status = (int)$request->getQueryParams()['status'];
        $assignee = (int)$request->getQueryParams()['assignee'];

        $pages = ContentUtility::getPagesByFilter($search, $status, $assignee);
        $result = [];
        foreach ($pages as $page) {
            $result[] = StatusItem::create($page)->toArray();
        }
        return new JsonResponse($result);
    }
}
