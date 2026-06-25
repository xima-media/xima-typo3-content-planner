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
use stdClass;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Event\PrepareStatusSelectionEvent;

/**
 * PrepareStatusSelectionEventTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class PrepareStatusSelectionEventTest extends TestCase
{
    #[Test]
    public function nameConstant(): void
    {
        self::assertSame('xima_typo3_content_planner.status.prepare_selection', PrepareStatusSelectionEvent::NAME);
    }

    #[Test]
    public function gettersReturnConstructorValues(): void
    {
        $context = new stdClass();
        $status = new Status();
        $status->setTitle('Draft');
        $selection = ['a' => 1];

        $event = new PrepareStatusSelectionEvent('pages', 7, $context, $selection, $status);

        self::assertSame('pages', $event->getTable());
        self::assertSame(7, $event->getUid());
        self::assertSame($context, $event->getContext());
        self::assertSame($selection, $event->getSelection());
        self::assertSame($status, $event->getCurrentStatus());
    }

    #[Test]
    public function nullableValuesAreAccepted(): void
    {
        $event = new PrepareStatusSelectionEvent('pages', null, new stdClass(), [], null);

        self::assertNull($event->getUid());
        self::assertNull($event->getCurrentStatus());
        self::assertSame([], $event->getSelection());
    }

    #[Test]
    public function selectionCanBeUpdated(): void
    {
        $event = new PrepareStatusSelectionEvent('pages', 1, new stdClass(), [], null);
        $event->setSelection(['new' => 'value']);

        self::assertSame(['new' => 'value'], $event->getSelection());
    }
}
