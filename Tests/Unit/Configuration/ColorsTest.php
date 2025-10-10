<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
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
 * @license GPL-2.0
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

    public function testGetColorWithTransparency(): void
    {
        $result = Colors::get('blue', true);
        self::assertSame('rgba(100,187,200, 0.5)', $result);
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

    public function testGetColorWithAllValidColorsAndTransparency(): void
    {
        $expectedResults = [
            'black' => 'rgba(144,164,174, 0.5)',
            'red' => 'rgba(250,136,147, 0.5)',
            'blue' => 'rgba(100,187,200, 0.5)',
            'yellow' => 'rgba(255,205,117, 0.5)',
            'green' => 'rgba(106,158,113, 0.5)',
            'purple' => 'rgba(92,107,192, 0.5)',
            'orange' => 'rgba(255,112,67, 0.5)',
        ];

        foreach ($expectedResults as $color => $expectedRgba) {
            self::assertSame($expectedRgba, Colors::get($color, true), "Color '{$color}' with transparency should return correct RGBA value");
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

    public function testColorNamingConvention(): void
    {
        foreach (Colors::STATUS_COLORS as $color) {
            // Colors should be lowercase and contain only letters
            self::assertMatchesRegularExpression('/^[a-z]+$/', $color, "Color '{$color}' should follow naming convention");
        }
    }
}
