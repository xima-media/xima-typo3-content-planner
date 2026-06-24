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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Manager;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use Xima\XimaTypo3ContentPlanner\Event\PrepareStatusSelectionEvent;
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;

/**
 * StatusSelectionManagerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class StatusSelectionManagerTest extends TestCase
{
    #[Test]
    public function prepareStatusSelectionUpdatesSelectionReferenceFromEvent(): void
    {
        $modifiedSelection = ['1' => 'Draft', '2' => 'Published'];

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (PrepareStatusSelectionEvent $event) use ($modifiedSelection): PrepareStatusSelectionEvent {
                $event->setSelection($modifiedSelection);

                return $event;
            });

        $manager = new StatusSelectionManager($eventDispatcher);

        $selection = [];
        $manager->prepareStatusSelection(new stdClass(), 'pages', 5, $selection);

        self::assertSame($modifiedSelection, $selection);
    }

    #[Test]
    public function prepareStatusSelectionPassesArgumentsToEvent(): void
    {
        $context = new stdClass();

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (PrepareStatusSelectionEvent $event) use ($context): PrepareStatusSelectionEvent {
                self::assertSame('tt_content', $event->getTable());
                self::assertSame(42, $event->getUid());
                self::assertSame($context, $event->getContext());
                self::assertSame(['initial' => 'value'], $event->getSelection());
                self::assertNull($event->getCurrentStatus());

                return $event;
            });

        $manager = new StatusSelectionManager($eventDispatcher);

        $selection = ['initial' => 'value'];
        $manager->prepareStatusSelection($context, 'tt_content', 42, $selection);

        self::assertSame(['initial' => 'value'], $selection);
    }
}
