<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\StatusItem;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;

class RecordController extends ActionController
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

    public function commentsAction(ServerRequestInterface $request): ResponseInterface
    {
        $recordId = (int)$request->getQueryParams()['uid'];
        $recordTable = $request->getQueryParams()['table'];
        $comments = ContentUtility::getComments($recordId, $recordTable);

        /** @var StandaloneView $view */
        $view = GeneralUtility::makeInstance(StandaloneView::class);

        $view->setTemplateRootPaths(['EXT:' . Configuration::EXT_KEY . '/Resources/Private/Templates']);
        $view->setPartialRootPaths(['EXT:' . Configuration::EXT_KEY . '/Resources/Private/Partials']);
        $view->setLayoutRootPaths(['EXT:' . Configuration::EXT_KEY . '/Resources/Private/Layouts']);

        $view->setTemplate('Comments');
        $view->assign('comments', $comments);
        return new JsonResponse(['result' => $view->render()]);
    }
}
