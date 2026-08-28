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
use ReflectionClass;
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

        self::assertSame(0, $status->getUid());
        self::assertSame('', $status->getTitle());
        self::assertSame('', $status->getIcon());
        self::assertSame('', $status->getColor());
        self::assertFalse($status->isDefault());
    }

    #[Test]
    public function constructorHydratesAllProperties(): void
    {
        $status = new Status(uid: 5, title: 'In Progress', icon: 'heart', color: 'yellow', isDefault: true);

        self::assertSame(5, $status->getUid());
        self::assertSame('In Progress', $status->getTitle());
        self::assertSame('heart', $status->getIcon());
        self::assertSame('yellow', $status->getColor());
        self::assertTrue($status->isDefault());
    }

    #[Test]
    public function coloredIconCombinesIconAndColor(): void
    {
        $status = new Status(icon: 'flag', color: 'red');

        self::assertSame('flag-red', $status->getColoredIcon());
    }

    #[Test]
    public function coloredIconWithEmptyValues(): void
    {
        $status = new Status();

        self::assertSame('-', $status->getColoredIcon());
    }

    #[Test]
    public function statusIsReadonly(): void
    {
        $reflection = new ReflectionClass(Status::class);

        self::assertTrue($reflection->isReadOnly());
    }
}
