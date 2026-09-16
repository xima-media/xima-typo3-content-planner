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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\EventListener\Watcher;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Model\WatchSource;
use Xima\XimaTypo3ContentPlanner\Event\CommentCreatedEvent;
use Xima\XimaTypo3ContentPlanner\EventListener\Watcher\AutoWatchOnCommentCreatedListener;
use Xima\XimaTypo3ContentPlanner\Service\WatcherService;

/**
 * AutoWatchOnCommentCreatedListenerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class AutoWatchOnCommentCreatedListenerTest extends TestCase
{
    #[Test]
    public function watchesTheCommentAuthor(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);
        $watcherService->expects(self::once())
            ->method('watch')
            ->with('pages', 42, 3, WatchSource::Comment);

        (new AutoWatchOnCommentCreatedListener($watcherService))(
            new CommentCreatedEvent(table: 'pages', recordUid: 42, commentUid: 7, authorUid: 3),
        );
    }

    #[Test]
    public function doesNothingWhenTableIsNotWatchable(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(false);
        $watcherService->expects(self::never())->method('watch');

        (new AutoWatchOnCommentCreatedListener($watcherService))(
            new CommentCreatedEvent(table: 'sys_file_metadata', recordUid: 42, commentUid: 7, authorUid: 3),
        );
    }
}
