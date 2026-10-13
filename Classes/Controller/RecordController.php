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
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\RequestId;
use TYPO3\CMS\Core\Http\{JsonResponse, RedirectResponse};
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\{CommentFilter, CommentListWindow, StatusItem};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Manager\{ChildCommentAggregationManager, CommentFirstFlowManager};
use Xima\XimaTypo3ContentPlanner\Service\AssigneeContextService;
use Xima\XimaTypo3ContentPlanner\Service\Header\InfoGenerator;
use Xima\XimaTypo3ContentPlanner\Service\RichText\CommentEditorConfigurationFactory;
use Xima\XimaTypo3ContentPlanner\Utility\Data\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\{AssetUtility, ViewUtility};
use Xima\XimaTypo3ContentPlanner\Utility\Routing\UrlUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function array_key_exists;
use function in_array;
use function is_array;

/**
 * RecordController.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class RecordController extends ActionController
{
    private const ALLOWED_USER_SETTINGS = ['repliesExpanded', 'includeChildComments'];
    private const MODAL_TABS = ['comments', 'assignee', 'watch'];

    public function __construct(
        private readonly RecordRepository $recordRepository,
        private readonly CommentRepository $commentRepository,
        private readonly BackendUserRepository $backendUserRepository,
        private readonly RequestId $requestId,
        private readonly CommentEditorConfigurationFactory $commentEditorConfigurationFactory,
        private readonly CommentFirstFlowManager $commentFirstFlowManager,
        private readonly ChildCommentAggregationManager $childCommentAggregationManager,
        private readonly AssigneeContextService $assigneeContextService,
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

        $record = $this->recordRepository->findByUid($table, $uid, true);
        if (!$record) {
            return new JsonResponse(['error' => 'Record not found'], 404);
        }

        if (!PermissionUtility::checkAccessForRecord($table, $record)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $extraParams = $this->buildShareRedirectParams($table, $uid, $commentUid, (string) ($request->getQueryParams()['tab'] ?? ''));
        $folderIdentifier = $record['folder_identifier'] ?? null;
        $redirectUrl = UrlUtility::getRecordLink($table, $uid, $folderIdentifier, $extraParams);

        return new RedirectResponse($redirectUrl, 302);
    }

    /**
     * The records the status dashboard widget lists: the 20 latest, whatever their status.
     * Searching and filtering is the records module's job.
     */
    public function latestAction(): JsonResponse
    {
        if (!PermissionUtility::checkContentStatusVisibility()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $filterResult = $this->recordRepository->findAllByFilter(null, null, null, null, null, 20);
        $items = [];
        foreach ($filterResult->items as $record) {
            $items[] = StatusItem::create($record)->toArray();
        }

        return new JsonResponse(['items' => $items, 'hasMore' => $filterResult->hasMore]);
    }

    public function commentsAction(ServerRequestInterface $request): JsonResponse
    {
        $recordId = (int) ($request->getQueryParams()['uid'] ?? 0);
        $recordTable = $request->getQueryParams()['table'] ?? '';
        $sortComments = $request->getQueryParams()['sortComments'] ?? 'DESC';
        $commentFilter = CommentFilter::fromQueryParams($request->getQueryParams());
        $showResolvedComments = $commentFilter->showResolved;
        $showTodoComments = $commentFilter->showTodo;

        if ('' === $recordTable || 0 === $recordId) {
            return new JsonResponse(['error' => 'Missing required parameters'], 400);
        }

        if (!PermissionUtility::checkContentStatusVisibility()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $record = $this->recordRepository->findByUid($recordTable, $recordId, true);
        if (!$record) {
            return new JsonResponse(['error' => 'Record not found'], 404);
        }

        if (!PermissionUtility::checkAccessForRecord($recordTable, $record)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];
        $repliesExpanded = (bool) ($backendUser->uc['contentPlanner']['repliesExpanded'] ?? false);
        $includeChildComments = (bool) ($backendUser->uc['contentPlanner']['includeChildComments'] ?? false);

        $comments = $this->commentRepository->findAllByRecord($recordId, $recordTable, false, $sortComments, $showResolvedComments, $showTodoComments);
        $openCount = $this->commentRepository->countAllByRecord($recordId, $recordTable);

        // CP-29 (#328): aggregated child comments - a view-only concern, only offered for pages
        // and only when the user opted in via the persisted "includeChildComments" setting.
        $childComments = $this->childCommentAggregationManager->buildContext($recordTable, $recordId, $includeChildComments, $showResolvedComments, $sortComments, $showTodoComments);
        $comments = $this->childCommentAggregationManager->mergeIntoList($comments, $childComments, $sortComments);
        $window = CommentListWindow::of($comments, $commentFilter, $sortComments);
        $resolvedCount = $this->commentRepository->countAllByRecord($recordId, $recordTable, false, true);

        $result = ViewUtility::render(
            'Default/Comments.html',
            [
                'comments' => $window->comments,
                'older' => $window,
                'id' => $recordId,
                'table' => $recordTable,
                'repliesExpanded' => $repliesExpanded,
                'currentUserUid' => (int) ($backendUser->user['uid'] ?? 0),
                ...$this->buildComposerContext($recordTable, $recordId, $record),
                'statusChange' => $this->commentFirstFlowManager->buildChangeContext($record),
                'childComments' => $childComments,
                'shareUrl' => UrlUtility::getShareUrl($recordTable, $recordId),
                'filter' => [
                    'sortComments' => $sortComments,
                    'show' => $commentFilter->show,
                    'openCount' => $openCount,
                    'todoCount' => $this->commentRepository->countCommentsWithTodosByRecord($recordId, $recordTable),
                    'resolvedCount' => $resolvedCount,
                    'allCount' => $openCount + $resolvedCount,
                    'loadAll' => $commentFilter->loadAll,
                    'includeChildComments' => $includeChildComments,
                    'isPage' => 'pages' === $recordTable,
                ],
            ],
        );

        // RecordModal.css owns the shared tab-bar shell (record-modal.js) - loaded explicitly
        // here rather than only via Assignee.css's @import, since the Comments tab is the
        // modal's default entry point and would otherwise never pull it in at all.
        $result .= AssetUtility::getCssTag('EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/RecordModal.css', ['nonce' => $this->requestId->nonce]);
        $result .= AssetUtility::getCssTag('EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Comments.css', ['nonce' => $this->requestId->nonce]);
        // No FormEngine around the composer, so nothing else brings the editor's own styles.
        if (ExtensionManagementUtility::isLoaded('rte_ckeditor')) {
            $result .= AssetUtility::getCssTag('EXT:rte_ckeditor/Resources/Public/Css/editor.css', ['nonce' => $this->requestId->nonce]);
        }
        $result .= AssetUtility::getJsTag('EXT:'.Configuration::EXT_KEY.'/Resources/Public/JavaScript/comments-reload-content.js', ['nonce' => $this->requestId->nonce]);

        return new JsonResponse([
            'result' => $result,
            // Read by the merged record modal's tab bar (record-modal.js) to show the count
            // next to the Comments tab label - the same unresolved count the trigger's own
            // badge already showed before the modal was opened (ChipTrioButtonBuilder).
            'commentsCount' => $this->commentRepository->countAllByRecord($recordId, $recordTable),
        ]);
    }

    /**
     * @throws Exception
     * @throws RouteNotFoundException
     */
    public function assigneeSelectionAction(ServerRequestInterface $request): ResponseInterface
    {
        $recordId = (int) ($request->getQueryParams()['uid'] ?? 0);
        $recordTable = $request->getQueryParams()['table'] ?? '';

        if ('' === $recordTable || 0 === $recordId) {
            return new JsonResponse(['error' => 'Missing required parameters'], 400);
        }

        if (!PermissionUtility::checkContentStatusVisibility()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $record = $this->recordRepository->findByUid($recordTable, $recordId, true);

        if (!$record) {
            return new JsonResponse(['error' => 'Record not found'], 404);
        }

        if (!PermissionUtility::checkAccessForRecord($recordTable, $record)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $currentAssignee = $this->resolveCurrentAssignee($request, $record);

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
                        ? UrlUtility::assignToUser($recordTable, $record['uid'], null, true)
                        : null,
                ],
                'currentInfo' => $this->assigneeContextService->describe($recordTable, $recordId, $currentAssignee),
                'canChangeAssignee' => $permissions['canChangeAssignee'],
                'assignSelfOnly' => $permissions['canAssignSelf'] && !$permissions['canAssignOthers'],
            ],
        );

        $result .= AssetUtility::getCssTag('EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/RecordModal.css', ['nonce' => $this->requestId->nonce]);
        $result .= AssetUtility::getCssTag('EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Assignee.css', ['nonce' => $this->requestId->nonce]);
        $result .= AssetUtility::getJsTag('EXT:'.Configuration::EXT_KEY.'/Resources/Public/JavaScript/assignee-select.js', ['nonce' => $this->requestId->nonce]);

        return new JsonResponse(['result' => $result]);
    }

    public function userSettingAction(ServerRequestInterface $request): JsonResponse
    {
        $key = $request->getQueryParams()['key'] ?? '';
        $value = $request->getQueryParams()['value'] ?? '';

        if (!in_array($key, self::ALLOWED_USER_SETTINGS, true)) {
            return new JsonResponse(['error' => 'Invalid setting key'], 400);
        }

        if (!in_array($value, ['0', '1'], true)) {
            return new JsonResponse(['error' => 'Invalid setting value'], 400);
        }

        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];
        $contentPlannerSettings = $backendUser->uc['contentPlanner'] ?? [];
        $contentPlannerSettings[$key] = '1' === $value;
        $backendUser->uc['contentPlanner'] = $contentPlannerSettings;
        $backendUser->writeUC();

        return new JsonResponse(['key' => $key, 'value' => $contentPlannerSettings[$key]]);
    }

    /**
     * Build redirect query parameters for the share action.
     *
     * @return array<string, int|string>
     */
    private function buildShareRedirectParams(string $table, int $uid, ?int $commentUid, string $tab = ''): array
    {
        $params = ['tx_contentplanner_comments' => 1];
        if (in_array($tab, self::MODAL_TABS, true)) {
            $params['tx_contentplanner_tab'] = $tab;
        }

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
     * The merged record modal's Comments trigger does not know the current assignee, so
     * switching to the Assignee tab from there sends no `currentAssignee` at all - fall back
     * to the record's own field so that tab still marks the right entry as current.
     *
     * @param array<string, mixed> $record
     */
    private function resolveCurrentAssignee(ServerRequestInterface $request, array $record): int
    {
        return array_key_exists('currentAssignee', $request->getQueryParams())
            ? (int) $request->getQueryParams()['currentAssignee']
            : (int) ($record[Configuration::FIELD_ASSIGNEE] ?? 0);
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
        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];
        $currentUserId = (int) ($backendUser->user['uid'] ?? 0);

        array_unshift($assignees, [
            'username' => $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:header.unassigned'),
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

    /**
     * What the comment composer needs, empty while the user may not create comments.
     *
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private function buildComposerContext(string $table, int $id, array $record): array
    {
        if (!PermissionUtility::canCreateComment()) {
            return ['newCommentUri' => '', 'commentComposerHtml' => '', 'commentFirst' => ['active' => false]];
        }

        return [
            'newCommentUri' => UrlUtility::getNewCommentUrl($table, $id),
            'commentComposerHtml' => $this->buildNewCommentComposerHtml($table, $id, (int) $record['pid']),
            // CP-27 (#326): comment-first flow - only relevant while the record has no status yet.
            'commentFirst' => $this->commentFirstFlowManager->buildContext($record),
        ];
    }

    /**
     * Renders the always-visible "new comment" composer embedded at the bottom of
     * Default/Comments.html (CP-28, #327) - unlike edit/reply, this one does not need an
     * on-demand AJAX round trip since it is always present when the record view loads.
     */
    private function buildNewCommentComposerHtml(string $table, int $id, int $recordPid): string
    {
        $pid = 'pages' === $table ? $id : $recordPid;
        $fieldId = 'tx-ximatypo3contentplanner-comment-new-'.$table.'-'.$id;
        $ckeditorConfiguration = $this->commentEditorConfigurationFactory->build($pid, $table, $id);

        return $this->commentEditorConfigurationFactory->buildEditorHtml($fieldId, $ckeditorConfiguration, '');
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
