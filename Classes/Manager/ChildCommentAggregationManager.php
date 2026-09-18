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

namespace Xima\XimaTypo3ContentPlanner\Manager;

use Doctrine\DBAL\Exception;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\CommentItem;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository};

use function count;
use function strtoupper;

/**
 * ChildCommentAggregationManager.
 *
 * CP-29 (#328): a page's own comment view shows only comments on the page record itself -
 * comments on its content elements, and on any other registered record living on that page
 * (e.g. a news record inside a sysfolder), are invisible from there even though editorially
 * they belong to the same conversation. This manager decides whether that aggregation should
 * run for the current view (`table`/`includeChildComments`) and, if so, returns those comments
 * for RecordController to merge into the record's own list.
 *
 * They are returned flat rather than grouped by record on purpose: a conversation reads in
 * chronological order, so a comment on a content element belongs between the page's own
 * comments of the same time, not in a section below all of them. Each item is flagged
 * `foreignRecord`, which is what makes the Comment partial draw the record marker (status,
 * type, jump link) beside it. `hasMore` (CP-16, #320) still signals that more commented child
 * records exist than fit the page.
 *
 * Deliberately a *view* concern only: the page's own comment count/tree badge is computed
 * elsewhere (RecordRepository::updateCommentsRelationByRecord()) from the page's own comments
 * alone and stays untouched by this aggregation - counters keep counting a record's own
 * comments, never its children's.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class ChildCommentAggregationManager
{
    public function __construct(
        private RecordRepository $recordRepository,
        private CommentRepository $commentRepository,
    ) {}

    /**
     * @return array{active: bool, count: int, items?: array<int, CommentItem>, hasMore?: bool}
     *
     * @throws Exception
     */
    public function buildContext(string $table, int $pageId, bool $includeChildComments, bool $showResolved = false, string $sortDirection = 'DESC', bool $showTodoComments = false): array
    {
        if ('pages' !== $table) {
            return ['active' => false, 'count' => 0];
        }

        $refsResult = $this->recordRepository->findChildRecordRefsWithComments($pageId, RecordRepository::DEFAULT_PAGE_SIZE);
        if ([] === $refsResult->items) {
            return ['active' => false, 'count' => 0];
        }

        $refs = array_map(
            static fn (array $row): array => ['table' => (string) $row['tablename'], 'uid' => (int) $row['uid']],
            $refsResult->items,
        );

        // The count is shown as a badge on the "show comments from child records" toggle even
        // while it's off (CP-29 follow-up), the same way the unrelated resolved-count badge is
        // always visible - so this lookup can no longer be skipped just because the toggle is off.
        $comments = $this->commentRepository->findAllByRecords($refs, $showResolved, $sortDirection, $showTodoComments);
        if ([] === $comments) {
            return ['active' => false, 'count' => 0];
        }

        if (!$includeChildComments) {
            return ['active' => false, 'count' => count($comments)];
        }

        foreach ($comments as $comment) {
            $comment->foreignRecord = true;
        }

        return [
            'active' => true,
            'count' => count($comments),
            'items' => $comments,
            'hasMore' => $refsResult->hasMore,
        ];
    }

    /**
     * Child-record comments read as part of the same conversation, so they are listed among the
     * record's own in the list's sort order rather than in a section of their own. Sorting the
     * merged list newest-first and reversing for ASC keeps it to a single comparison.
     *
     * @param array<int, CommentItem>                                          $comments
     * @param array{active: bool, count: int, items?: array<int, CommentItem>} $context
     *
     * @return array<int, CommentItem>
     */
    public function mergeIntoList(array $comments, array $context, string $sortDirection): array
    {
        $childItems = $context['active'] ? ($context['items'] ?? []) : [];
        if ([] === $childItems) {
            return $comments;
        }

        $merged = [...$comments, ...$childItems];
        usort($merged, static fn (CommentItem $a, CommentItem $b): int => self::lastActivity($b) <=> self::lastActivity($a));

        return 'ASC' === strtoupper($sortDirection) ? array_reverse($merged) : $merged;
    }

    /**
     * The repository orders root comments by last_activity, not crdate, so a thread with a fresh
     * reply floats to the top (see CommentRepository::buildRootCommentsQueryBuilder()). Merging
     * has to keep that key, otherwise switching the child-comment toggle on would silently
     * reshuffle the record's own comments.
     */
    private static function lastActivity(CommentItem $comment): int
    {
        return (int) ($comment->data['last_activity'] ?? $comment->data['crdate']);
    }
}
