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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Configuration;

use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Configuration\Icons;

final class IconsTest extends TestCase
{
    public function testStatusIconConstants(): void
    {
        self::assertSame('flag', Icons::STATUS_ICON_FLAG);
        self::assertSame('heart', Icons::STATUS_ICON_HEART);
        self::assertSame('tag', Icons::STATUS_ICON_TAG);
        self::assertSame('star', Icons::STATUS_ICON_STAR);
        self::assertSame('info', Icons::STATUS_ICON_INFO);
        self::assertSame('certificate', Icons::STATUS_ICON_CERTIFICATE);
        self::assertSame('exclamation', Icons::STATUS_ICON_EXCLAMATION);
        self::assertSame('rocket', Icons::STATUS_ICON_ROCKET);
        self::assertSame('thumbtack', Icons::STATUS_ICON_THUMBTACK);
    }

    public function testStatusIconsArray(): void
    {
        $expectedIcons = [
            'flag',
            'heart',
            'tag',
            'star',
            'info',
            'certificate',
            'exclamation',
            'rocket',
            'thumbtack',
        ];

        self::assertSame($expectedIcons, Icons::STATUS_ICONS);
    }

    public function testStatusIconsArrayContainsAllConstants(): void
    {
        $expectedIcons = [
            Icons::STATUS_ICON_FLAG,
            Icons::STATUS_ICON_HEART,
            Icons::STATUS_ICON_TAG,
            Icons::STATUS_ICON_STAR,
            Icons::STATUS_ICON_INFO,
            Icons::STATUS_ICON_CERTIFICATE,
            Icons::STATUS_ICON_EXCLAMATION,
            Icons::STATUS_ICON_ROCKET,
            Icons::STATUS_ICON_THUMBTACK,
        ];

        self::assertSame($expectedIcons, Icons::STATUS_ICONS);
    }

    public function testAllIconsAreUnique(): void
    {
        $uniqueIcons = array_unique(Icons::STATUS_ICONS);
        self::assertCount(count(Icons::STATUS_ICONS), $uniqueIcons, 'All icons should be unique');
    }

    public function testAllIconsAreStrings(): void
    {
        foreach (Icons::STATUS_ICONS as $icon) {
            self::assertNotEmpty($icon, 'Icon should not be empty');
        }
    }

    public function testIconNamingConvention(): void
    {
        foreach (Icons::STATUS_ICONS as $icon) {
            // Icons should be lowercase and contain only letters
            self::assertMatchesRegularExpression('/^[a-z]+$/', $icon, "Icon '{$icon}' should follow naming convention");
        }
    }
}
