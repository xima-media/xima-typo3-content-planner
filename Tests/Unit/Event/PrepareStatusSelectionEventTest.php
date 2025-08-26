<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "xima_typo3_content_planner".
 *
 * Copyright (C) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Event\PrepareStatusSelectionEvent;

class PrepareStatusSelectionEventTest extends TestCase
{
    private PrepareStatusSelectionEvent $subject;
    private Status $currentStatus;
    private object $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->currentStatus = new Status();
        $this->context = new \stdClass();

        $this->subject = new PrepareStatusSelectionEvent(
            'pages',
            123,
            $this->context,
            ['status_1' => 'Draft', 'status_2' => 'Review'],
            $this->currentStatus
        );
    }

    public function testEventName(): void
    {
        self::assertSame('xima_typo3_content_planner.status.prepare_selection', PrepareStatusSelectionEvent::NAME);
    }

    public function testGetTable(): void
    {
        self::assertSame('pages', $this->subject->getTable());
    }

    public function testGetUid(): void
    {
        self::assertSame(123, $this->subject->getUid());
    }

    public function testGetUidWithNullValue(): void
    {
        $event = new PrepareStatusSelectionEvent(
            'tt_content',
            null,
            new \stdClass(),
            [],
            null
        );

        self::assertNull($event->getUid());
    }

    public function testGetSelection(): void
    {
        $expected = ['status_1' => 'Draft', 'status_2' => 'Review'];
        self::assertSame($expected, $this->subject->getSelection());
    }

    public function testSetAndGetSelection(): void
    {
        $newSelection = ['status_3' => 'Published', 'status_4' => 'Archived'];
        $this->subject->setSelection($newSelection);
        self::assertSame($newSelection, $this->subject->getSelection());
    }

    public function testGetCurrentStatus(): void
    {
        self::assertSame($this->currentStatus, $this->subject->getCurrentStatus());
    }

    public function testGetCurrentStatusWithNull(): void
    {
        $event = new PrepareStatusSelectionEvent(
            'pages',
            456,
            new \stdClass(),
            ['status_1' => 'Draft'],
            null
        );

        self::assertNull($event->getCurrentStatus());
    }

    public function testGetContext(): void
    {
        self::assertSame($this->context, $this->subject->getContext());
    }

    public function testConstructorWithAllParameters(): void
    {
        $status = new Status();
        $context = new \stdClass();
        $context->type = 'test';
        $selection = ['status_5' => 'Final Review'];

        $event = new PrepareStatusSelectionEvent(
            'sys_category',
            789,
            $context,
            $selection,
            $status
        );

        self::assertSame('sys_category', $event->getTable());
        self::assertSame(789, $event->getUid());
        self::assertSame($context, $event->getContext());
        self::assertSame($selection, $event->getSelection());
        self::assertSame($status, $event->getCurrentStatus());
    }

    public function testSelectionManipulation(): void
    {
        // Simulate workflow example from documentation
        $selection = $this->subject->getSelection();

        // Remove a status from selection
        unset($selection['status_2']);

        // Add a new status to selection
        $selection['status_3'] = 'Published';

        $this->subject->setSelection($selection);

        $expected = ['status_1' => 'Draft', 'status_3' => 'Published'];
        self::assertSame($expected, $this->subject->getSelection());
    }

    public function testEmptySelection(): void
    {
        $event = new PrepareStatusSelectionEvent(
            'pages',
            100,
            new \stdClass(),
            [],
            null
        );

        self::assertSame([], $event->getSelection());

        $newSelection = ['status_1' => 'New Status'];
        $event->setSelection($newSelection);
        self::assertSame($newSelection, $event->getSelection());
    }

    public function testComplexContextObject(): void
    {
        $context = new class () {
            public string $type = 'backend';
            /** @var array<int, string> */
            public array $permissions = ['read', 'write'];

            public function getType(): string
            {
                return $this->type;
            }
        };

        $event = new PrepareStatusSelectionEvent(
            'pages',
            999,
            $context,
            ['status_1' => 'Test'],
            null
        );

        $retrievedContext = $event->getContext();
        self::assertSame('backend', $retrievedContext->type);
        self::assertSame(['read', 'write'], $retrievedContext->permissions);
        self::assertSame('backend', $retrievedContext->getType());
    }
}
