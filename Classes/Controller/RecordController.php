<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Controller;

use Doctrine\DBAL\Exception;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Core\Core\RequestId;
use TYPO3\CMS\Core\Http\{JsonResponse, RedirectResponse};
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\StatusItem;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Service\Header\InfoGenerator;
use Xima\XimaTypo3ContentPlanner\Utility\Data\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\{AssetUtility, ViewUtility};
use Xima\XimaTypo3ContentPlanner\Utility\Routing\UrlUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function array_key_exists;
use function is_array;

/**
 * RecordController.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class RecordController extends ActionController
{
    public function __construct(
        private readonly RecordRepository $recordRepository,
        private readonly CommentRepository $commentRepository,
        private readonly BackendUserRepository $backendUserRepository,
        private readonly RequestId $requestId,
    ) {}

    /**
     * @throws RouteNotFoundException
     */
    public function shareAction(ServerRequestInterface $request): ResponseInterface
    {
        $table = $request->getQueryParams()['table'] ?? '';
        $uid = (int) ($request->getQueryParams()['uid'] ?? 0);
        $commentUid = isset($request->getQueryParams()['comment'])
            ? (int) $request->getQueryParams()['comment']
            : null;

        if ('' === $table || 0 === $uid) {
            return new JsonResponse(['error' => 'Missing required parameters'], 400);
        }

        if (!PermissionUtility::checkContentStatusVisibility()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $record = $this->recordRepository->findByUid($table, $uid, ignoreVisibilityRestriction: true);
        if (!$record) {
            return new JsonResponse(['error' => 'Record not found'], 404);
        }

        if (!PermissionUtility::checkAccessForRecord($table, $record)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $extraParams = $this->buildShareRedirectParams($table, $uid, $commentUid);
        $folderIdentifier = $record['folder_identifier'] ?? null;
        $redirectUrl = UrlUtility::getRecordLink($table, $uid, $folderIdentifier, $extraParams);

        return new RedirectResponse($redirectUrl, 302);
    }

    public function filterAction(ServerRequestInterface $request): ResponseInterface
    {
        $search = array_key_exists('search', $request->getQueryParams()) ? $request->getQueryParams()['search'] : null;
        $status = array_key_exists('status', $request->getQueryParams()) ? (int) $request->getQueryParams()['status'] : null;
        $assignee = array_key_exists('assignee', $request->getQueryParams()) ? (int) $request->getQueryParams()['assignee'] : null;
        $todo = array_key_exists('todo', $request->getQueryParams()) ? (bool) $request->getQueryParams()['todo'] : false;
        $type = array_key_exists('type', $request->getQueryParams()) ? $request->getQueryParams()['type'] : null;
        $openComments = array_key_exists('openComments', $request->getQueryParams()) ? (bool) $request->getQueryParams()['openComments'] : false;

        $records = $this->recordRepository->findAllByFilter($search, $status, assignee: $assignee, type: $type, todo: $todo, openComments: $openComments);
        $result = [];
        foreach ($records as $record) {
            $result[] = StatusItem::create($record)->toArray();
        }

        return new JsonResponse($result);
    }

    public function commentsAction(ServerRequestInterface $request): ResponseInterface
    {
        $recordId = (int) $request->getQueryParams()['uid'];
        $recordTable = $request->getQueryParams()['table'];
        $sortComments = $request->getQueryParams()['sortComments'] ?? 'DESC';
        $showResolvedComments = (bool) ($request->getQueryParams()['showResolvedComments'] ?? false);

        $comments = $this->commentRepository->findAllByRecord($recordId, $recordTable, sortDirection: $sortComments, showResolved: $showResolvedComments);

        $result = ViewUtility::render(
            'Default/Comments.html',
            [
                'comments' => $comments,
                'id' => $recordId,
                'table' => $recordTable,
                'newCommentUri' => PermissionUtility::canCreateComment() ? UrlUtility::getNewCommentUrl($recordTable, $recordId) : '',
                'shareUrl' => UrlUtility::getShareUrl($recordTable, $recordId),
                'filter' => [
                    'sortComments' => $sortComments,
                    'showResolvedComments' => $showResolvedComments,
                    'resolvedCount' => $this->commentRepository->countAllByRecord($recordId, $recordTable, onlyResolved: true),
                ],
            ],
        );

        $result .= AssetUtility::getCssTag('EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Comments.css', ['nonce' => $this->requestId->nonce]);
        $result .= AssetUtility::getJsTag('EXT:'.Configuration::EXT_KEY.'/Resources/Public/JavaScript/comments-reload-content.js', ['nonce' => $this->requestId->nonce]);

        return new JsonResponse(['result' => $result]);
    }

    /**
     * @throws Exception
     * @throws RouteNotFoundException
     */
    public function assigneeSelectionAction(ServerRequestInterface $request): ResponseInterface
    {
        $recordId = (int) $request->getQueryParams()['uid'];
        $recordTable = $request->getQueryParams()['table'];
        $currentAssignee = (int) ($request->getQueryParams()['currentAssignee'] ?? 0);

        $record = $this->recordRepository->findByUid($recordTable, $recordId, ignoreVisibilityRestriction: true);

        if (!$record) {
            return new JsonResponse(['error' => 'Record not found'], 404);
        }

        $permissions = $this->getAssignmentPermissions();
        $assignees = $this->prepareAssigneeList($recordTable, $recordId, $currentAssignee, $permissions);

        $result = ViewUtility::render(
            'Default/Assignees.html',
            [
                'recordId' => $recordId,
                'assignees' => $assignees,
                'assignee' => [
                    'current' => $currentAssignee,
                    'assignToCurrentUser' => $permissions['canAssignSelf'] && InfoGenerator::checkAssignToCurrentUser($record)
                        ? UrlUtility::assignToUser($recordTable, $record['uid'])
                        : false,
                    'unassign' => InfoGenerator::canUnassignRecord($record) && InfoGenerator::checkUnassign($record)
                        ? UrlUtility::assignToUser($recordTable, $record['uid'], unassign: true)
                        : null,
                ],
                'canChangeAssignee' => $permissions['canChangeAssignee'],
            ],
        );

        $result .= AssetUtility::getJsTag('EXT:'.Configuration::EXT_KEY.'/Resources/Public/JavaScript/assignee-select.js', ['nonce' => $this->requestId->nonce]);

        return new JsonResponse(['result' => $result]);
    }

    /**
     * Build redirect query parameters for the share action.
     *
     * @return array<string, int>
     */
    private function buildShareRedirectParams(string $table, int $uid, ?int $commentUid): array
    {
        $params = ['tx_contentplanner_comments' => 1];

        if (null === $commentUid) {
            return $params;
        }

        $comment = $this->commentRepository->findByUid($commentUid);
        if (!is_array($comment) || $comment['foreign_table'] !== $table || (int) $comment['foreign_uid'] !== $uid) {
            return $params;
        }

        $params['tx_contentplanner_comment'] = $commentUid;
        if ((int) ($comment['resolved_date'] ?? 0) > 0) {
            $params['tx_contentplanner_comment_resolved'] = 1;
        }

        return $params;
    }

    /**
     * Get assignment permissions for the current user.
     *
     * @return array{canAssignSelf: bool, canAssignOthers: bool, canChangeAssignee: bool}
     */
    private function getAssignmentPermissions(): array
    {
        $canAssignSelf = PermissionUtility::canAssignSelf();
        $canAssignOthers = PermissionUtility::canAssignOthers();

        return [
            'canAssignSelf' => $canAssignSelf,
            'canAssignOthers' => $canAssignOthers,
            'canChangeAssignee' => $canAssignSelf || $canAssignOthers,
        ];
    }

    /**
     * Prepare the list of assignees with proper URLs based on permissions.
     *
     * @param array{canAssignSelf: bool, canAssignOthers: bool, canChangeAssignee: bool} $permissions
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws RouteNotFoundException
     */
    private function prepareAssigneeList(string $table, int $recordId, int $currentAssignee, array $permissions): array
    {
        $assignees = $this->backendUserRepository->findAllWithPermission();
        $currentUserId = (int) ($GLOBALS['BE_USER']->user['uid'] ?? 0);

        array_unshift($assignees, [
            'username' => '-- Not assigned --',
            'realName' => '',
            'uid' => 0,
        ]);

        foreach ($assignees as &$assignee) {
            $assigneeUid = (int) ($assignee['uid'] ?? 0);
            $assignee['uid'] = $assigneeUid;
            $assignee['name'] = ContentUtility::generateDisplayName($assignee);
            $assignee['isCurrent'] = $assigneeUid === $currentAssignee;
            $assignee['url'] = $this->canAssignToUser($assigneeUid, $currentUserId, $currentAssignee, $permissions)
                ? UrlUtility::assignToUser($table, $recordId, $assigneeUid)
                : '';
        }

        usort($assignees, static fn ($a, $b) => ((int) $b['uid'] === $currentAssignee) <=> ((int) $a['uid'] === $currentAssignee));

        return $assignees;
    }

    /**
     * Check if user can assign to a specific user in the dropdown list.
     *
     * @param array{canAssignSelf: bool, canAssignOthers: bool, canChangeAssignee: bool} $permissions
     */
    private function canAssignToUser(int $targetUserId, int $currentUserId, int $currentAssignee, array $permissions): bool
    {
        if (!$permissions['canChangeAssignee']) {
            return false;
        }

        if ($permissions['canAssignOthers']) {
            return true;
        }

        // assign-self only: can assign yourself or unassign yourself
        if (0 === $targetUserId) {
            return $currentAssignee === $currentUserId;
        }

        return $targetUserId === $currentUserId;
    }
}
