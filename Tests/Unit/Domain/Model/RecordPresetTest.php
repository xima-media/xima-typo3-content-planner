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
use Xima\XimaTypo3ContentPlanner\Domain\Model\RecordPreset;

/**
 * RecordPresetTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class RecordPresetTest extends TestCase
{
    #[Test]
    public function casesNameTheQueryParameterOfTheRecordsModule(): void
    {
        self::assertSame('assignee', RecordPreset::Assignee->value);
        self::assertSame('todo', RecordPreset::Todo->value);
        self::assertSame('openComments', RecordPreset::OpenComments->value);
    }

    #[Test]
    public function theAssigneePresetFiltersByTheBackendUserAndTheOthersAreSwitches(): void
    {
        self::assertSame(7, RecordPreset::Assignee->filterValue(7));
        self::assertSame(1, RecordPreset::Todo->filterValue(7));
        self::assertSame(1, RecordPreset::OpenComments->filterValue(7));
    }
}
