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
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Event\StatusChangeEvent;

/**
 * StatusChangeEventTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class StatusChangeEventTest extends TestCase
{
    #[Test]
    public function nameConstant(): void
    {
        self::assertSame('xima_typo3_content_planner.status.change', StatusChangeEvent::NAME);
    }

    #[Test]
    public function gettersReturnConstructorValues(): void
    {
        $previous = new Status();
        $previous->setTitle('Draft');
        $new = new Status();
        $new->setTitle('Published');

        $event = new StatusChangeEvent('pages', 5, ['title' => 'foo'], $previous, $new);

        self::assertSame('pages', $event->getTable());
        self::assertSame(5, $event->getUid());
        self::assertSame(['title' => 'foo'], $event->getFieldArray());
        self::assertSame($previous, $event->getPreviousStatus());
        self::assertSame($new, $event->getNewStatus());
    }

    #[Test]
    public function nullableStatusesAreAccepted(): void
    {
        $event = new StatusChangeEvent('tt_content', 1, [], null, null);

        self::assertNull($event->getPreviousStatus());
        self::assertNull($event->getNewStatus());
    }

    #[Test]
    public function fieldArrayCanBeUpdated(): void
    {
        $event = new StatusChangeEvent('pages', 1, [], null, null);
        $event->setFieldArray(['hidden' => 1]);

        self::assertSame(['hidden' => 1], $event->getFieldArray());
    }

    #[Test]
    public function newStatusCanBeUpdated(): void
    {
        $event = new StatusChangeEvent('pages', 1, [], null, null);
        $status = new Status();
        $status->setTitle('Review');
        $event->setNewStatus($status);

        self::assertSame($status, $event->getNewStatus());

        $event->setNewStatus(null);
        self::assertNull($event->getNewStatus());
    }
}
