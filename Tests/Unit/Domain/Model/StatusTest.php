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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;

/**
 * StatusTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class StatusTest extends TestCase
{
    #[Test]
    public function defaultValuesAreEmptyStrings(): void
    {
        $status = new Status();

        self::assertSame('', $status->getTitle());
        self::assertSame('', $status->getIcon());
        self::assertSame('', $status->getColor());
    }

    #[Test]
    public function titleCanBeSetAndRetrieved(): void
    {
        $status = new Status();
        $status->setTitle('In Progress');

        self::assertSame('In Progress', $status->getTitle());
    }

    #[Test]
    public function iconCanBeSetAndRetrieved(): void
    {
        $status = new Status();
        $status->setIcon('flag');

        self::assertSame('flag', $status->getIcon());
    }

    #[Test]
    public function colorCanBeSetAndRetrieved(): void
    {
        $status = new Status();
        $status->setColor('red');

        self::assertSame('red', $status->getColor());
    }

    #[Test]
    public function coloredIconCombinesIconAndColor(): void
    {
        $status = new Status();
        $status->setIcon('flag');
        $status->setColor('red');

        self::assertSame('flag-red', $status->getColoredIcon());
    }

    #[Test]
    public function coloredIconWithEmptyValues(): void
    {
        $status = new Status();

        self::assertSame('-', $status->getColoredIcon());
    }
}
