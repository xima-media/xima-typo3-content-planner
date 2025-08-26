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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Domain\Model;

use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;

class StatusTest extends TestCase
{
    private Status $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new Status();
    }

    public function testInitialState(): void
    {
        self::assertSame('', $this->subject->getTitle());
        self::assertSame('', $this->subject->getIcon());
        self::assertSame('', $this->subject->getColor());
        self::assertSame('-', $this->subject->getColoredIcon());
    }

    public function testSetAndGetTitle(): void
    {
        $title = 'In Progress';
        $this->subject->setTitle($title);
        self::assertSame($title, $this->subject->getTitle());
    }

    public function testSetAndGetIcon(): void
    {
        $icon = 'clock';
        $this->subject->setIcon($icon);
        self::assertSame($icon, $this->subject->getIcon());
    }

    public function testSetAndGetColor(): void
    {
        $color = 'blue';
        $this->subject->setColor($color);
        self::assertSame($color, $this->subject->getColor());
    }

    public function testGetColoredIcon(): void
    {
        $this->subject->setIcon('check');
        $this->subject->setColor('green');
        self::assertSame('check-green', $this->subject->getColoredIcon());
    }

    public function testGetColoredIconWithEmptyValues(): void
    {
        self::assertSame('-', $this->subject->getColoredIcon());
    }

    public function testGetColoredIconWithOnlyIcon(): void
    {
        $this->subject->setIcon('warning');
        self::assertSame('warning-', $this->subject->getColoredIcon());
    }

    public function testGetColoredIconWithOnlyColor(): void
    {
        $this->subject->setColor('red');
        self::assertSame('-red', $this->subject->getColoredIcon());
    }
}
