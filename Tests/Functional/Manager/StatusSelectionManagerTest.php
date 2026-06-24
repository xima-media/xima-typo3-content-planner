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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Manager;

use PHPUnit\Framework\Attributes\Test;
use stdClass;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use Xima\XimaTypo3ContentPlanner\Event\PrepareStatusSelectionEvent;
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * StatusSelectionManagerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class StatusSelectionManagerTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function prepareStatusSelectionReturnsSelectionFromDispatchedEvent(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(static fn (PrepareStatusSelectionEvent $event): PrepareStatusSelectionEvent => $event);

        $subject = new StatusSelectionManager($eventDispatcher);

        $selection = ['a' => 'first', 'b' => 'second'];
        $context = new stdClass();
        $subject->prepareStatusSelection($context, 'pages', 1, $selection);

        self::assertSame(['a' => 'first', 'b' => 'second'], $selection);
    }

    #[Test]
    public function prepareStatusSelectionPassesContextAndTableToEvent(): void
    {
        $capturedEvent = null;
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(static function (PrepareStatusSelectionEvent $event) use (&$capturedEvent): PrepareStatusSelectionEvent {
                $capturedEvent = $event;

                return $event;
            });

        $subject = new StatusSelectionManager($eventDispatcher);

        $selection = [];
        $context = new stdClass();
        $subject->prepareStatusSelection($context, 'tt_content', 42, $selection);

        self::assertInstanceOf(PrepareStatusSelectionEvent::class, $capturedEvent);
        self::assertSame('tt_content', $capturedEvent->getTable());
    }
}
