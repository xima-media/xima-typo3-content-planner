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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Event\CommentCreatedEvent;

/**
 * CommentCreatedEventTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CommentCreatedEventTest extends TestCase
{
    public function testGetters(): void
    {
        $event = new CommentCreatedEvent(
            table: 'pages',
            recordUid: 42,
            commentUid: 7,
            authorUid: 3,
        );

        self::assertSame('pages', $event->getTable());
        self::assertSame(42, $event->getRecordUid());
        self::assertSame(7, $event->getCommentUid());
        self::assertSame(3, $event->getAuthorUid());
    }
}
