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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Event\AssigneeChangedEvent;

/**
 * AssigneeChangedEventTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class AssigneeChangedEventTest extends TestCase
{
    #[Test]
    public function gettersReturnConstructorValues(): void
    {
        $event = new AssigneeChangedEvent('pages', 5, 3, 9, 1);

        self::assertSame('pages', $event->getTable());
        self::assertSame(5, $event->getUid());
        self::assertSame(3, $event->getPreviousAssignee());
        self::assertSame(9, $event->getNewAssignee());
        self::assertSame(1, $event->getActorUid());
    }

    #[Test]
    public function nullableValuesAreAccepted(): void
    {
        $event = new AssigneeChangedEvent('pages', 1, null, null, null);

        self::assertNull($event->getPreviousAssignee());
        self::assertNull($event->getNewAssignee());
        self::assertNull($event->getActorUid());
    }
}
