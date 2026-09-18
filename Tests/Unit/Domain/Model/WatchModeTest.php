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

use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Model\WatchMode;

use function array_unique;
use function count;

/**
 * WatchModeTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class WatchModeTest extends TestCase
{
    /**
     * @return iterable<string, array{WatchMode|null, string}>
     */
    public static function iconProvider(): iterable
    {
        yield 'not watching' => [null, 'content-planner-bell'];
        yield 'auto' => [WatchMode::Auto, 'content-planner-bell-ringing'];
        yield 'manual watch' => [WatchMode::ManualWatch, 'content-planner-bell-filled'];
        yield 'manual unwatch' => [WatchMode::ManualUnwatch, 'content-planner-bell-off'];
    }

    /**
     * @return iterable<string, array{WatchMode|null, string}>
     */
    public static function stateProvider(): iterable
    {
        yield 'not watching' => [null, 'inactive'];
        yield 'auto' => [WatchMode::Auto, 'auto'];
        yield 'manual watch' => [WatchMode::ManualWatch, 'manual'];
        yield 'manual unwatch' => [WatchMode::ManualUnwatch, 'muted'];
    }

    #[Test]
    #[DataProvider('iconProvider')]
    public function iconIdentifierMapsEveryStateToItsBell(?WatchMode $mode, string $expected): void
    {
        self::assertSame($expected, WatchMode::iconIdentifier($mode));
    }

    #[Test]
    #[DataProvider('stateProvider')]
    public function presentationStateMapsEveryStateToItsCssModifier(?WatchMode $mode, string $expected): void
    {
        self::assertSame($expected, WatchMode::presentationState($mode));
    }

    /**
     * The header renders the toggle icon-only, so colour alone must never be what tells two
     * states apart (WCAG 1.4.1). Guards against a future state reusing an existing bell.
     */
    #[Test]
    public function everyStateUsesADistinctIcon(): void
    {
        $icons = [];
        foreach (self::iconProvider() as $case) {
            $icons[] = WatchMode::iconIdentifier($case[0]);
        }

        self::assertCount(count($icons), array_unique($icons));
    }
}
