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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Utility\Data;

use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Utility\Data\DiffUtility;

/**
 * DiffUtilityTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class DiffUtilityTest extends TestCase
{
    protected function setUp(): void
    {
        // Mock the global LANG object needed by DiffUtility
        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->willReturnCallback(fn (string $key): string =>
            // Simple mock implementation that returns the key itself
            match (true) {
                str_contains($key, 'timeAgo.now') => 'now',
                str_contains($key, 'timeAgo.years') => '%d years ago',
                str_contains($key, 'timeAgo.year') => '%d year ago',
                str_contains($key, 'timeAgo.months') => '%d months ago',
                str_contains($key, 'timeAgo.month') => '%d month ago',
                str_contains($key, 'timeAgo.days') => '%d days ago',
                str_contains($key, 'timeAgo.day') => '%d day ago',
                str_contains($key, 'timeAgo.hours') => '%d hours ago',
                str_contains($key, 'timeAgo.hour') => '%d hour ago',
                str_contains($key, 'timeAgo.minutes') => '%d minutes ago',
                str_contains($key, 'timeAgo.minute') => '%d minute ago',
                str_contains($key, 'timeAgo.seconds') => '%d seconds ago',
                default => $key,
            });

        $GLOBALS['LANG'] = $languageServiceMock;
    }

    public function testTimeAgoCurrentTime(): void
    {
        $currentTimestamp = time();

        $result = DiffUtility::timeAgo($currentTimestamp);

        self::assertSame('now', $result);
    }

    public function testTimeAgoOneHour(): void
    {
        $oneHourAgo = time() - 3600;

        $result = DiffUtility::timeAgo($oneHourAgo);

        self::assertSame('1 hour ago', $result);
    }

    public function testTimeAgoMultipleHours(): void
    {
        $threeHoursAgo = time() - (3 * 3600);

        $result = DiffUtility::timeAgo($threeHoursAgo);

        self::assertSame('3 hours ago', $result);
    }

    public function testTimeAgoOneDay(): void
    {
        $oneDayAgo = time() - 86400;

        $result = DiffUtility::timeAgo($oneDayAgo);

        self::assertSame('1 day ago', $result);
    }

    public function testTimeAgoMultipleDays(): void
    {
        $threeDaysAgo = time() - (3.5 * 86400);

        $result = DiffUtility::timeAgo((int) ceil($threeDaysAgo));

        self::assertSame('3 days ago', $result);
    }

    public function testTimeAgoOneMinute(): void
    {
        $oneMinuteAgo = time() - 60;

        $result = DiffUtility::timeAgo($oneMinuteAgo);

        self::assertSame('1 minute ago', $result);
    }

    public function testCheckRecordDiffWithNoDifferences(): void
    {
        $data = [
            'oldRecord' => [
                'title' => 'Test Page',
                'status' => 1,
            ],
            'newRecord' => [
                'title' => 'Test Page',
                'status' => 1,
            ],
        ];

        $result = DiffUtility::checkRecordDiff($data, 1);

        self::assertFalse($result);
    }

    public function testCheckRecordDiffWithDifferences(): void
    {
        $data = [
            'oldRecord' => [
                'title' => 'Old Title',
                'l10n_diffsource' => 'ignored',
            ],
            'newRecord' => [
                'title' => 'New Title',
                'l10n_diffsource' => 'ignored_too',
            ],
        ];

        $result = DiffUtility::checkRecordDiff($data, 1);

        // Result should contain some diff information (not false)
        self::assertIsString($result);
        self::assertNotEmpty($result);
    }

    public function testTimeAgoWithFutureTimestamp(): void
    {
        $futureTimestamp = time() + 3600; // 1 hour in the future

        $result = DiffUtility::timeAgo($futureTimestamp);

        // Should still return a time difference string
        self::assertNotEmpty($result);
    }

    public function testTimeAgoWithZeroTimestamp(): void
    {
        $result = DiffUtility::timeAgo(0);

        // Should return years ago (since 1970)
        self::assertStringContainsString('year', $result);
    }

    public function testTimeAgoWithLargeTimeDifference(): void
    {
        $twoYearsAgo = time() - (2 * 365 * 24 * 3600);

        $result = DiffUtility::timeAgo($twoYearsAgo);

        self::assertStringContainsString('year', $result);
    }

    public function testTimeAgoWithSeconds(): void
    {
        $secondsAgo = time() - 30;

        $result = DiffUtility::timeAgo($secondsAgo);

        self::assertStringContainsString('second', $result);
    }

    public function testCheckRecordDiffSkipsL10nDiffsource(): void
    {
        $data = [
            'oldRecord' => [
                'title' => 'Old Title',
                'l10n_diffsource' => 'should_be_ignored_old',
            ],
            'newRecord' => [
                'title' => 'Old Title', // Same title, no diff
                'l10n_diffsource' => 'should_be_ignored_new',
            ],
        ];

        $result = DiffUtility::checkRecordDiff($data, 1);

        // Should return false because l10n_diffsource is ignored and title is the same
        self::assertFalse($result);
    }

    public function testCheckRecordDiffWithEmptyOldRecord(): void
    {
        $data = [
            'oldRecord' => [],
            'newRecord' => [
                'title' => 'New Title',
            ],
        ];

        $result = DiffUtility::checkRecordDiff($data, 1);

        // Should return false because there are no old values to compare
        self::assertFalse($result);
    }
}
