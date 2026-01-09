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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Configuration;

use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Configuration\Icons;

use function count;

/**
 * IconsTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
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
