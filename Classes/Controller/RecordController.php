<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\RequestId;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\StatusItem;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\UrlHelper;
use Xima\XimaTypo3ContentPlanner\Utility\ViewFactoryHelper;

class RecordController extends ActionController
{
    public function __construct(
        private readonly RecordRepository $recordRepository,
        private readonly CommentRepository $commentRepository,
        private readonly RequestId $requestId
    ) {
    }

    public function filterAction(ServerRequestInterface $request): ResponseInterface
    {
        $search = array_key_exists('search', $request->getQueryParams()) ? $request->getQueryParams()['search'] : null;
        $status = array_key_exists('status', $request->getQueryParams()) ? (int)$request->getQueryParams()['status'] : null;
        $assignee = array_key_exists('assignee', $request->getQueryParams()) ? (int)$request->getQueryParams()['assignee'] : null;
        $todo = array_key_exists('todo', $request->getQueryParams()) ? (bool)$request->getQueryParams()['todo'] : false;
        $type = array_key_exists('type', $request->getQueryParams()) ? $request->getQueryParams()['type'] : null;

        $records = $this->recordRepository->findAllByFilter($search, $status, assignee: $assignee, type: $type, todo: $todo);
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
        $sortComments = $request->getQueryParams()['sortComments'] ?? 'DESC';
        $showResolvedComments = (bool)($request->getQueryParams()['showResolvedComments'] ?? false);

        $comments = $this->commentRepository->findAllByRecord($recordId, $recordTable, sortDirection: $sortComments, showResolved: $showResolvedComments);

        $result = ViewFactoryHelper::renderView(
            'Default/Comments.html',
            [
                'comments' => $comments,
                'id' => $recordId,
                'table' => $recordTable,
                'newCommentUri' => UrlHelper::getNewCommentUrl($recordTable, $recordId),
                'filter' => [
                    'sortComments' => $sortComments,
                    'showResolvedComments' => $showResolvedComments,
                    'resolvedCount' => $this->commentRepository->countAllByRecord($recordId, $recordTable, onlyResolved: true),
                ],
            ]
        );

        $result .= ExtensionUtility::getCssTag('EXT:' . Configuration::EXT_KEY . '/Resources/Public/Css/Comments.css', ['nonce' => $this->requestId->nonce]);
        $result .= ExtensionUtility::getJsTag('EXT:' . Configuration::EXT_KEY . '/Resources/Public/JavaScript/comments-reload-content.js', ['nonce' => $this->requestId->nonce]);

        return new JsonResponse(['result' => $result]);
    }
}
