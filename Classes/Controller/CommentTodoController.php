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
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Utility\Data\TodoToggleUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function array_key_exists;
use function is_array;

/**
 * CommentTodoController.
 *
 * Inline to-do checkbox toggle (CP-30, #389): flips a single to-do checkbox directly from the
 * comment display state, without opening the comment for editing. Persists through the
 * DataHandler - the same write path a full comment edit uses - so permission checks, the
 * todo_total/todo_resolved recalculation and the "edited" flag (skipped for this marker, see
 * DataHandlerHook::checkCommentEdited()) behave consistently with editing the comment text
 * itself.
 *
 * Deliberately its own controller, separate from the comment CRUD that still runs through
 * TYPO3's record_edit iframe modal on this branch line: a future native comment composer would
 * introduce its own CommentEditorController, which this class must not collide with.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class CommentTodoController extends ActionController
{
    public function __construct(
        private readonly CommentRepository $commentRepository,
        private readonly RecordRepository $recordRepository,
    ) {}

    /**
     * @throws Exception
     */
    public function toggleTodoAction(ServerRequestInterface $request): JsonResponse
    {
        if (!PermissionUtility::checkContentStatusVisibility()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $body = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $commentUid = (int) ($body['commentUid'] ?? 0);
        $todoIndex = (int) ($body['todoIndex'] ?? -1);

        if ($commentUid <= 0 || $todoIndex < 0 || !array_key_exists('checked', $body)) {
            return new JsonResponse(['error' => 'Missing required parameters'], 400);
        }

        $checked = (bool) $body['checked'];

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

        // The header badge (HeaderInfo.html) shows the to-do count summed across every comment
        // on the record, not just this one - the same aggregate InfoGenerator computes for the
        // initial page render, so the frontend can patch that badge without reloading the whole
        // comment list.
        $foreignUid = (int) $comment['foreign_uid'];
        $foreignTable = (string) $comment['foreign_table'];

        return new JsonResponse([
            'commentUid' => $commentUid,
            'todoResolved' => (int) $updated['todo_resolved'],
            'todoTotal' => (int) $updated['todo_total'],
            'recordTodoResolved' => $this->commentRepository->countTodoAllByRecord($foreignUid, $foreignTable),
            'recordTodoTotal' => $this->commentRepository->countTodoAllByRecord($foreignUid, $foreignTable, 'todo_total'),
        ]);
    }

    /**
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

        // canEditComment() only checks the content-planner comment-edit permission, not whether
        // the current user may access the record the comment is attached to (mirrors
        // RecordController::commentsAction(), which checks both for the same reason: knowing a
        // comment's uid must not let a user reach a record outside their normal TYPO3 access).
        $record = $this->recordRepository->findByUid((string) $comment['foreign_table'], (int) $comment['foreign_uid'], true);
        if (!PermissionUtility::checkAccessForRecord((string) $comment['foreign_table'], $record)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        return $comment;
    }
}
