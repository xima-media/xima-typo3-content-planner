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
use Xima\XimaTypo3ContentPlanner\Event\StatusChangeEvent;
use Xima\XimaTypo3ContentPlanner\EventListener\Watcher\AutoWatchOnStatusChangeListener;
use Xima\XimaTypo3ContentPlanner\Service\WatcherService;

/**
 * AutoWatchOnStatusChangeListenerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class AutoWatchOnStatusChangeListenerTest extends TestCase
{
    #[Test]
    public function watchesTheActor(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(true);
        $watcherService->expects(self::once())
            ->method('watch')
            ->with('pages', 5, 1, WatchSource::StatusChange);

        (new AutoWatchOnStatusChangeListener($watcherService))(
            new StatusChangeEvent('pages', 5, [], null, null, 1),
        );
    }

    #[Test]
    public function doesNothingWhenActorIsUnknown(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->expects(self::never())->method('watch');

        (new AutoWatchOnStatusChangeListener($watcherService))(
            new StatusChangeEvent('pages', 5, [], null, null, null),
        );
    }

    #[Test]
    public function doesNothingWhenTableIsNotWatchable(): void
    {
        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->method('isWatchable')->willReturn(false);
        $watcherService->expects(self::never())->method('watch');

        (new AutoWatchOnStatusChangeListener($watcherService))(
            new StatusChangeEvent('sys_file_metadata', 5, [], null, null, 1),
        );
    }
}
