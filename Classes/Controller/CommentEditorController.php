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
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\{GeneralUtility, StringUtility};
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\CommentItem;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Service\RichText\CommentEditorConfigurationFactory;
use Xima\XimaTypo3ContentPlanner\Utility\Data\TodoToggleUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\ViewUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function is_array;
use function strip_tags;
use function trim;

/**
 * CommentEditorController.
 *
 * Native comment composer (CP-28, #327): renders the inline edit/reply composer fragment and
 * persists comment create/edit/reply through the DataHandler, replacing the former
 * record_edit iframe modal. Split out of RecordController to keep each controller focused on
 * one concern and its cognitive complexity in check.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class CommentEditorController extends ActionController
{
    public function __construct(
        private readonly RecordRepository $recordRepository,
        private readonly CommentRepository $commentRepository,
        private readonly CommentEditorConfigurationFactory $commentEditorConfigurationFactory,
    ) {}

    /**
     * Renders the inline comment composer fragment for editing an existing comment or
     * replying to one. Creating a new root comment uses the composer already embedded in
     * Default/Comments.html (see RecordController::commentsAction()), so this action only
     * serves edit/reply.
     *
     * @throws RouteNotFoundException
     */
    public function commentEditorAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!PermissionUtility::checkContentStatusVisibility()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $params = $request->getQueryParams();
        $commentUid = (int) ($params['commentUid'] ?? 0);

        if ($commentUid > 0) {
            return $this->renderEditCommentEditorFragment($commentUid);
        }

        return $this->renderReplyCommentEditorFragment($params);
    }

    /**
     * Persists a comment through the DataHandler - the same write path and guarantees the
     * former record_edit form used: DataHandlerHook applies permission checks, to-do parsing
     * and "edited" flagging, dispatches CommentCreatedEvent/CommentResolvedEvent, and updates
     * the comment-count relation on the parent record.
     *
     * @throws Exception
     */
    public function commentSaveAction(ServerRequestInterface $request): JsonResponse
    {
        if (!PermissionUtility::checkContentStatusVisibility()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $content = trim((string) ($body['content'] ?? ''));
        $commentUid = (int) ($body['commentUid'] ?? 0);

        if ('' === strip_tags($content)) {
            return new JsonResponse(['error' => 'Comment content must not be empty'], 400);
        }

        if ($commentUid > 0) {
            return $this->saveCommentEdit($commentUid, $content);
        }

        return $this->saveNewComment($body, $content);
    }

    /**
     * Toggles a single to-do checkbox from the comment display state (CP-30, #389), without
     * opening the composer. Persists through the DataHandler - the same write path
     * commentSaveAction() uses for whole-comment edits - so permission checks, the
     * todo_total/todo_resolved recalculation and comment events behave identically to editing
     * the comment text itself.
     *
     * @throws Exception
     */
    public function commentToggleTodoAction(ServerRequestInterface $request): JsonResponse
    {
        if (!PermissionUtility::checkContentStatusVisibility()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $commentUid = (int) ($body['commentUid'] ?? 0);
        $todoIndex = (int) ($body['todoIndex'] ?? -1);
        $checked = (bool) ($body['checked'] ?? false);

        if ($commentUid <= 0 || $todoIndex < 0) {
            return new JsonResponse(['error' => 'Missing required parameters'], 400);
        }

        $comment = $this->resolveEditableComment($commentUid);
        if ($comment instanceof JsonResponse) {
            return $comment;
        }

        $content = TodoToggleUtility::toggle((string) $comment['content'], $todoIndex, $checked);
        if (null === $content) {
            return new JsonResponse(['error' => 'To-do item not found'], 400);
        }

        /** @var DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            Configuration::TABLE_COMMENT => [
                $commentUid => [
                    'content' => $content,
                    // Not a TCA column, never persisted - read by
                    // DataHandlerHook::checkCommentEdited() to tell a to-do toggle apart from a
                    // real text edit.
                    '__todoToggle' => true,
                ],
            ],
        ], []);
        $dataHandler->process_datamap();

        if ([] !== $dataHandler->errorLog) {
            return new JsonResponse(['error' => 'Failed to save comment'], 500);
        }

        // DataHandlerHook::updateCommentTodo() already recalculated and wrote todo_total/
        // todo_resolved into this same datamap before the save went through - no need for a
        // second SELECT just to read them back.
        $updated = $dataHandler->datamap[Configuration::TABLE_COMMENT][$commentUid];

        return new JsonResponse([
            'commentUid' => $commentUid,
            'todoResolved' => (int) $updated['todo_resolved'],
            'todoTotal' => (int) $updated['todo_total'],
        ]);
    }

    /**
     * @throws RouteNotFoundException
     */
    private function renderEditCommentEditorFragment(int $commentUid): ResponseInterface
    {
        $comment = $this->commentRepository->findByUid($commentUid);
        if (!is_array($comment)) {
            return new JsonResponse(['error' => 'Comment not found'], 404);
        }
        if (!PermissionUtility::canEditComment($comment)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        return $this->renderCommentEditorFragment(
            'edit',
            (string) $comment['foreign_table'],
            (int) $comment['foreign_uid'],
            (int) $comment['parent_uid'],
            $commentUid,
            (string) $comment['content'],
            (int) $comment['pid'],
        );
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws RouteNotFoundException
     */
    private function renderReplyCommentEditorFragment(array $params): ResponseInterface
    {
        $table = (string) ($params['table'] ?? '');
        $id = (int) ($params['uid'] ?? 0);
        $parentUid = (int) ($params['parentUid'] ?? 0);

        if ('' === $table || 0 === $id || 0 === $parentUid) {
            return new JsonResponse(['error' => 'Missing required parameters'], 400);
        }

        if (!PermissionUtility::canCreateComment()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $record = $this->resolveAccessibleRecord($table, $id);
        if ($record instanceof JsonResponse) {
            return $record;
        }

        if (!$this->parentCommentBelongsToRecord($parentUid, $table, $id)) {
            return new JsonResponse(['error' => 'Invalid parent comment'], 400);
        }

        return $this->renderCommentEditorFragment(
            'reply',
            $table,
            $id,
            $parentUid,
            0,
            '',
            'pages' === $table ? $id : (int) $record['pid'],
        );
    }

    /**
     * @throws RouteNotFoundException
     */
    private function renderCommentEditorFragment(string $mode, string $table, int $id, int $parentUid, int $commentUid, string $content, int $pid): JsonResponse
    {
        $ckeditorConfiguration = $this->commentEditorConfigurationFactory->build($pid);
        $fieldId = 'tx-ximatypo3contentplanner-comment-'.$mode.'-'.$commentUid.'-'.$table.'-'.$id.'-'.$parentUid;

        $result = ViewUtility::render(
            'Default/CommentEditor.html',
            [
                'mode' => $mode,
                'table' => $table,
                'id' => $id,
                'parentUid' => $parentUid,
                'commentUid' => $commentUid,
                'editorHtml' => $this->commentEditorConfigurationFactory->buildEditorHtml($fieldId, $ckeditorConfiguration, $content),
            ],
        );

        return new JsonResponse(['result' => $result]);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @throws Exception
     */
    private function saveNewComment(array $body, string $content): JsonResponse
    {
        $table = (string) ($body['table'] ?? '');
        $id = (int) ($body['uid'] ?? 0);
        $parentUid = (int) ($body['parentUid'] ?? 0);

        if ('' === $table || 0 === $id) {
            return new JsonResponse(['error' => 'Missing required parameters'], 400);
        }

        if (!PermissionUtility::canCreateComment()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $record = $this->resolveAccessibleRecord($table, $id);
        if ($record instanceof JsonResponse) {
            return $record;
        }

        if ($parentUid > 0 && !$this->parentCommentBelongsToRecord($parentUid, $table, $id)) {
            return new JsonResponse(['error' => 'Invalid parent comment'], 400);
        }

        $pid = 'pages' === $table ? $id : (int) $record['pid'];
        $newId = StringUtility::getUniqueId('NEW');
        $data = [
            Configuration::TABLE_COMMENT => [
                $newId => [
                    'foreign_uid' => $id,
                    'foreign_table' => $table,
                    'content' => $content,
                    'pid' => $pid,
                    'parent_uid' => $parentUid,
                ],
            ],
        ];

        // DataHandler is inherently a per-operation, stateful object, never a shared service -
        // creating it via makeInstance() here (rather than injecting it) is the correct TYPO3
        // pattern, not a DI shortcut (see PlannerService::addCommentsToRecord()).
        /** @var DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();

        if ([] !== $dataHandler->errorLog) {
            return new JsonResponse(['error' => 'Failed to save comment'], 500);
        }

        $resolvedUid = $dataHandler->substNEWwithIDs[$newId] ?? null;
        if (null === $resolvedUid) {
            return new JsonResponse(['error' => 'Failed to save comment'], 500);
        }

        return $this->renderSavedComment((int) $resolvedUid, $table, $id);
    }

    /**
     * @throws Exception
     */
    private function saveCommentEdit(int $commentUid, string $content): JsonResponse
    {
        $comment = $this->resolveEditableComment($commentUid);
        if ($comment instanceof JsonResponse) {
            return $comment;
        }

        /** @var DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([Configuration::TABLE_COMMENT => [$commentUid => ['content' => $content]]], []);
        $dataHandler->process_datamap();

        if ([] !== $dataHandler->errorLog) {
            return new JsonResponse(['error' => 'Failed to save comment'], 500);
        }

        return $this->renderSavedComment($commentUid, (string) $comment['foreign_table'], (int) $comment['foreign_uid']);
    }

    /**
     * @throws Exception
     */
    private function renderSavedComment(int $commentUid, string $table, int $recordId): JsonResponse
    {
        $comment = $this->commentRepository->findByUid($commentUid);
        if (!is_array($comment)) {
            return new JsonResponse(['error' => 'Comment not found after save'], 500);
        }

        $result = ViewUtility::render(
            'Default/CommentFragment.html',
            [
                'comment' => CommentItem::create($comment),
                'id' => $recordId,
                'table' => $table,
                'isReply' => (int) $comment['parent_uid'] > 0 ? 1 : 0,
                'repliesExpanded' => false,
            ],
        );

        return new JsonResponse([
            'result' => $result,
            'commentUid' => $commentUid,
            'parentUid' => (int) $comment['parent_uid'],
            'commentCount' => $this->commentRepository->countAllByRecord($recordId, $table),
        ]);
    }

    private function parentCommentBelongsToRecord(int $parentUid, string $table, int $id): bool
    {
        $parentComment = $this->commentRepository->findByUid($parentUid);

        return is_array($parentComment) && $parentComment['foreign_table'] === $table && (int) $parentComment['foreign_uid'] === $id;
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function resolveAccessibleRecord(string $table, int $uid): array|JsonResponse
    {
        $record = $this->recordRepository->findByUid($table, $uid, true);
        if (!$record) {
            return new JsonResponse(['error' => 'Record not found'], 404);
        }

        if (!PermissionUtility::checkAccessForRecord($table, $record)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        return $record;
    }

    /**
     * Shared by saveCommentEdit() and commentToggleTodoAction(): both need the existing
     * comment and its content-edit permission before touching the DataHandler.
     *
     * @return array<string, mixed>|JsonResponse
     */
    private function resolveEditableComment(int $commentUid): array|JsonResponse
    {
        $comment = $this->commentRepository->findByUid($commentUid);
        if (!is_array($comment)) {
            return new JsonResponse(['error' => 'Comment not found'], 404);
        }

        if (!PermissionUtility::canEditComment($comment)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        return $comment;
    }
}
