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
use Xima\XimaTypo3ContentPlanner\Event\StatusChangeEvent;

class StatusChangeEventTest extends TestCase
{
    private StatusChangeEvent $subject;
    private Status $previousStatus;
    private Status $newStatus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousStatus = new Status();
        $this->newStatus = new Status();

        $this->subject = new StatusChangeEvent(
            'pages',
            123,
            ['title' => 'Test Page'],
            $this->previousStatus,
            $this->newStatus
        );
    }

    public function testEventName(): void
    {
        self::assertSame('xima_typo3_content_planner.status.change', StatusChangeEvent::NAME);
    }

    public function testGetTable(): void
    {
        self::assertSame('pages', $this->subject->getTable());
    }

    public function testGetUid(): void
    {
        self::assertSame(123, $this->subject->getUid());
    }

    public function testGetFieldArray(): void
    {
        $expected = ['title' => 'Test Page'];
        self::assertSame($expected, $this->subject->getFieldArray());
    }

    public function testSetAndGetFieldArray(): void
    {
        $newFieldArray = ['title' => 'Updated Page', 'assignee' => 42];
        $this->subject->setFieldArray($newFieldArray);
        self::assertSame($newFieldArray, $this->subject->getFieldArray());
    }

    public function testGetPreviousStatus(): void
    {
        self::assertSame($this->previousStatus, $this->subject->getPreviousStatus());
    }

    public function testGetNewStatus(): void
    {
        self::assertSame($this->newStatus, $this->subject->getNewStatus());
    }

    public function testSetAndGetNewStatus(): void
    {
        $anotherStatus = new Status();
        $this->subject->setNewStatus($anotherStatus);
        self::assertSame($anotherStatus, $this->subject->getNewStatus());
    }

    public function testConstructorWithNullStatuses(): void
    {
        $event = new StatusChangeEvent(
            'tt_content',
            456,
            ['header' => 'Content Element'],
            null,
            null
        );

        self::assertSame('tt_content', $event->getTable());
        self::assertSame(456, $event->getUid());
        self::assertSame(['header' => 'Content Element'], $event->getFieldArray());
        self::assertNull($event->getPreviousStatus());
        self::assertNull($event->getNewStatus());
    }

    public function testSetNewStatusToNull(): void
    {
        self::assertSame($this->newStatus, $this->subject->getNewStatus());
        $this->subject->setNewStatus(null);
        self::assertNull($this->subject->getNewStatus());
    }

    public function testFieldArrayModification(): void
    {
        $fieldArray = $this->subject->getFieldArray();
        $fieldArray['assignee'] = 99;
        $this->subject->setFieldArray($fieldArray);

        $expected = ['title' => 'Test Page', 'assignee' => 99];
        self::assertSame($expected, $this->subject->getFieldArray());
    }
}
