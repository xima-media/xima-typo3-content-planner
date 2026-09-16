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

/**
 * ChildCommentAggregationManager.
 *
 * CP-29 (#328): a page's own comment view shows only comments on the page record itself -
 * comments on its content elements, and on any other registered record living on that page
 * (e.g. a news record inside a sysfolder), are invisible from there even though editorially
 * they belong to the same conversation. This manager decides whether that aggregation should
 * run for the current view (`table`/`includeChildComments`) and, if so, builds the grouped
 * child-comment context for Default/Comments.html: one group per child record (its type icon,
 * title, and deep link via the existing shareAction/getRecordLink infrastructure on
 * {@see CommentItem}) plus a `hasMore` signal (CP-16, #320) when more commented child records
 * exist than fit the page.
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
     * @return array{active: bool, count: int, groups?: array<int, array{icon: string, title: string, recordLink: string, comments: array<int, CommentItem>}>, hasMore?: bool}
     *
     * @throws Exception
     */
    public function buildContext(string $table, int $pageId, bool $includeChildComments, bool $showResolved = false, string $sortDirection = 'DESC'): array
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
        $comments = $this->commentRepository->findAllByRecords($refs, $showResolved, $sortDirection);
        if ([] === $comments) {
            return ['active' => false, 'count' => 0];
        }

        if (!$includeChildComments) {
            return ['active' => false, 'count' => count($comments)];
        }

        return [
            'active' => true,
            'count' => count($comments),
            'groups' => $this->groupByRecord($comments),
            'hasMore' => $refsResult->hasMore,
        ];
    }

    /**
     * @param array<int, CommentItem> $comments
     *
     * @return array<int, array{icon: string, title: string, recordLink: string, comments: array<int, CommentItem>}>
     */
    private function groupByRecord(array $comments): array
    {
        $groups = [];
        foreach ($comments as $comment) {
            $key = $comment->data['foreign_table'].':'.$comment->data['foreign_uid'];

            $groups[$key] ??= [
                'icon' => $comment->getRecordIcon(),
                'title' => $comment->getTitle(),
                'recordLink' => $comment->getRecordLink(),
                'comments' => [],
            ];
            $groups[$key]['comments'][] = $comment;
        }

        return array_values($groups);
    }
}
