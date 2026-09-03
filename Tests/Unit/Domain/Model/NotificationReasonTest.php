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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{NotificationReason, WatchSource};

/**
 * NotificationReasonTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class NotificationReasonTest extends TestCase
{
    /**
     * @return iterable<string, array{WatchSource, NotificationReason}>
     */
    public static function watchSourceMappingProvider(): iterable
    {
        yield 'assignment' => [WatchSource::Assignment, NotificationReason::WatchingSinceAssignment];
        yield 'comment' => [WatchSource::Comment, NotificationReason::WatchingSinceComment];
        yield 'status change' => [WatchSource::StatusChange, NotificationReason::WatchingSinceStatusChange];
        yield 'manual' => [WatchSource::Manual, NotificationReason::WatchingManually];
        yield 'mention' => [WatchSource::Mention, NotificationReason::Mentioned];
    }

    #[Test]
    #[DataProvider('watchSourceMappingProvider')]
    public function fromWatchSourceMapsEveryWatchSourceToItsReason(WatchSource $source, NotificationReason $expected): void
    {
        self::assertSame($expected, NotificationReason::fromWatchSource($source));
    }
}
