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
use TYPO3\CMS\Core\Http\JsonResponse;
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

    public function filterAction(ServerRequestInterface $request): ResponseInterface
    {
        $search = array_key_exists('search', $request->getQueryParams()) ? $request->getQueryParams()['search'] : null;
        $status = array_key_exists('status', $request->getQueryParams()) ? (int) $request->getQueryParams()['status'] : null;
        $assignee = array_key_exists('assignee', $request->getQueryParams()) ? (int) $request->getQueryParams()['assignee'] : null;
        $todo = array_key_exists('todo', $request->getQueryParams()) ? (bool) $request->getQueryParams()['todo'] : false;
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

        $permissions = $this->getAssignmentPermissions($currentAssignee);
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
                    'unassign' => $permissions['canReassign'] && InfoGenerator::checkUnassign($record)
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
     * Get assignment permissions for the current user.
     *
     * @return array{canAssignSelf: bool, canAssignOther: bool, canReassign: bool, canChangeAssignee: bool}
     */
    private function getAssignmentPermissions(int $currentAssignee): array
    {
        $canAssignSelf = PermissionUtility::canAssignSelf();
        $canAssignOther = PermissionUtility::canAssignOtherUser();
        $canReassign = PermissionUtility::canReassign();
        $hasExistingAssignee = $currentAssignee > 0;

        return [
            'canAssignSelf' => $canAssignSelf,
            'canAssignOther' => $canAssignOther,
            'canReassign' => $canReassign,
            'canChangeAssignee' => $canAssignOther
                || ($canAssignSelf && !$hasExistingAssignee)
                || ($canReassign && $hasExistingAssignee),
        ];
    }

    /**
     * Prepare the list of assignees with proper URLs based on permissions.
     *
     * @param array{canAssignSelf: bool, canAssignOther: bool, canReassign: bool, canChangeAssignee: bool} $permissions
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
            $assignee['url'] = $this->canAssignToUser($assigneeUid, $currentUserId, $permissions)
                ? UrlUtility::assignToUser($table, $recordId, $assigneeUid)
                : '';
        }

        usort($assignees, static fn ($a, $b) => ((int) $b['uid'] === $currentAssignee) <=> ((int) $a['uid'] === $currentAssignee));

        return $assignees;
    }

    /**
     * Check if user can assign to a specific user.
     *
     * @param array{canAssignSelf: bool, canAssignOther: bool, canReassign: bool, canChangeAssignee: bool} $permissions
     */
    private function canAssignToUser(int $targetUserId, int $currentUserId, array $permissions): bool
    {
        if (!$permissions['canChangeAssignee']) {
            return false;
        }

        if ($permissions['canAssignOther']) {
            return true;
        }

        if (0 === $targetUserId) {
            return true; // Unassign is always allowed if canChangeAssignee is true
        }

        return $permissions['canAssignSelf'] && $targetUserId === $currentUserId;
    }
}
