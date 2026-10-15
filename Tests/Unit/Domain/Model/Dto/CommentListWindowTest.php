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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Domain\Model\Dto;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\{CommentFilter, CommentItem, CommentListWindow};

/**
 * CommentListWindowTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CommentListWindowTest extends TestCase
{
    #[Test]
    public function showsEverythingUpToTheLimit(): void
    {
        $window = CommentListWindow::of($this->comments(20), CommentFilter::fromQueryParams([]), 'DESC');

        self::assertCount(20, $window->comments);
        self::assertSame(0, $window->olderCount);
    }

    #[Test]
    public function keepsTheFirstTwentyAndCountsTheRestWhenNewestComeFirst(): void
    {
        $window = CommentListWindow::of($this->comments(25), CommentFilter::fromQueryParams([]), 'DESC');

        self::assertCount(20, $window->comments);
        self::assertSame(1, $window->comments[0]->data['uid']);
        self::assertSame(5, $window->olderCount);
        self::assertFalse($window->olderFirst);
    }

    #[Test]
    public function keepsTheLastTwentyWhenOldestComeFirst(): void
    {
        $window = CommentListWindow::of($this->comments(25), CommentFilter::fromQueryParams([]), 'ASC');

        self::assertSame(6, $window->comments[0]->data['uid']);
        self::assertSame(5, $window->olderCount);
        self::assertTrue($window->olderFirst);
    }

    #[Test]
    public function loadsEverythingWhenAsked(): void
    {
        $window = CommentListWindow::of($this->comments(25), CommentFilter::fromQueryParams(['all' => '1']), 'DESC');

        self::assertCount(25, $window->comments);
        self::assertSame(0, $window->olderCount);
    }

    #[Test]
    public function keepsOnlyResolvedCommentsForTheResolvedChoice(): void
    {
        $comments = [$this->comment(1, 0), $this->comment(2, 1700000000), $this->comment(3, 0), $this->comment(4, 1700000001)];

        $window = CommentListWindow::of($comments, CommentFilter::fromQueryParams(['show' => 'resolved']), 'DESC');

        self::assertSame([2, 4], array_map(static fn (CommentItem $comment): int => $comment->data['uid'], $window->comments));
    }

    /**
     * @return array<int, CommentItem>
     */
    private function comments(int $count): array
    {
        return array_map(fn (int $uid): CommentItem => $this->comment($uid, 0), range(1, $count));
    }

    private function comment(int $uid, int $resolvedDate): CommentItem
    {
        return CommentItem::create(['uid' => $uid, 'resolved_date' => $resolvedDate]);
    }
}
