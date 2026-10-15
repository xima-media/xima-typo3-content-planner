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

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Configuration\Colors;

use function count;

/**
 * ColorsTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ColorsTest extends TestCase
{
    public function testStatusColorConstants(): void
    {
        self::assertSame('black', Colors::STATUS_COLOR_BLACK);
        self::assertSame('blue', Colors::STATUS_COLOR_BLUE);
        self::assertSame('green', Colors::STATUS_COLOR_GREEN);
        self::assertSame('yellow', Colors::STATUS_COLOR_YELLOW);
        self::assertSame('red', Colors::STATUS_COLOR_RED);
        self::assertSame('purple', Colors::STATUS_COLOR_PURPLE);
        self::assertSame('orange', Colors::STATUS_COLOR_ORANGE);
    }

    public function testStatusColorsArray(): void
    {
        $expectedColors = [
            'black',
            'blue',
            'green',
            'yellow',
            'red',
            'purple',
            'orange',
        ];

        self::assertSame($expectedColors, Colors::STATUS_COLORS);
    }

    public function testStatusColorsArrayContainsAllConstants(): void
    {
        $expectedColors = [
            Colors::STATUS_COLOR_BLACK,
            Colors::STATUS_COLOR_BLUE,
            Colors::STATUS_COLOR_GREEN,
            Colors::STATUS_COLOR_YELLOW,
            Colors::STATUS_COLOR_RED,
            Colors::STATUS_COLOR_PURPLE,
            Colors::STATUS_COLOR_ORANGE,
        ];

        self::assertSame($expectedColors, Colors::STATUS_COLORS);
    }

    public function testAllColorsAreUnique(): void
    {
        $uniqueColors = array_unique(Colors::STATUS_COLORS);
        self::assertCount(count(Colors::STATUS_COLORS), $uniqueColors, 'All colors should be unique');
    }

    public function testGetColorWithValidColorCode(): void
    {
        $result = Colors::get('red');
        self::assertSame('rgb(250,136,147)', $result);
    }

    public function testGetColorWithAllValidColors(): void
    {
        $expectedResults = [
            'black' => 'rgb(144,164,174)',
            'red' => 'rgb(250,136,147)',
            'blue' => 'rgb(100,187,200)',
            'yellow' => 'rgb(255,205,117)',
            'green' => 'rgb(106,158,113)',
            'purple' => 'rgb(92,107,192)',
            'orange' => 'rgb(255,112,67)',
        ];

        foreach ($expectedResults as $color => $expectedRgb) {
            self::assertSame($expectedRgb, Colors::get($color), "Color '{$color}' should return correct RGB value");
        }
    }

    public function testGetColorWithInvalidColorCodeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid color code');
        $this->expectExceptionCode(2653877737);

        Colors::get('invalid_color');
    }

    public function testGetColorWithEmptyStringThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Colors::get('');
    }

    public function testGetHexWithAllValidColors(): void
    {
        $expectedResults = [
            'black' => '#90a4ae',
            'blue' => '#64bbc8',
            'green' => '#6a9e71',
            'yellow' => '#ffcd75',
            'red' => '#fa8893',
            'purple' => '#5c6bc0',
            'orange' => '#ff7043',
        ];

        foreach ($expectedResults as $color => $expectedHex) {
            self::assertSame($expectedHex, Colors::getHex($color), "Color '{$color}' should return correct hex value");
        }
    }

    public function testGetHexWithInvalidColorCodeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid color code');
        $this->expectExceptionCode(2653877738);

        Colors::getHex('invalid_color');
    }

    public function testGetEdgeOnlyLimitsTheLightnessOfTheBaseColour(): void
    {
        self::assertSame(
            'light-dark(oklch(from #fa8893 min(l, 0.6) c h), oklch(from #fa8893 max(l, 0.72) c h))',
            Colors::getEdge('red'),
        );
    }

    public function testGetTintMixesTheDarkSchemeWithTheCoreSurface(): void
    {
        self::assertSame(
            'light-dark(color-mix(in srgb, #ffcd75 15%, white), color-mix(in srgb, #ffcd75 20%, var(--typo3-surface-base)))',
            Colors::getTint('yellow'),
        );
    }

    public function testGetEdgeWithInvalidColorCodeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Colors::getEdge('invalid_color');
    }

    public function testColorNamingConvention(): void
    {
        foreach (Colors::STATUS_COLORS as $color) {
            // Colors should be lowercase and contain only letters
            self::assertMatchesRegularExpression('/^[a-z]+$/', $color, "Color '{$color}' should follow naming convention");
        }
    }
}
