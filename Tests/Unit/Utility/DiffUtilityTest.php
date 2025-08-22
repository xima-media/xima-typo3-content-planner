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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\DiffUtility;

final class DiffUtilityTest extends UnitTestCase
{
    /**
     * @return array<string, array{input: int, expected: string}>
     */
    public static function timeAgo(): array
    {
        return [
//            'currentTime' => [
//                'input' => time(),
//                'expected' => 'now',
//            ],
//            'oneSecondAgo' => [
//                'input' => time() - 1,
//                'expected' => '1 seconds ago',
//            ],
            'oneMinuteAgo' => [
                'input' => time() - 60,
                'expected' => '1 minute ago',
            ],
            'oneHourAgo' => [
                'input' => time() - 3600,
                'expected' => '1 hour ago',
            ],
            'threeHoursAgo' => [
                'input' => time() - (3 * 3600),
                'expected' => '3 hours ago',
            ],
            'oneDayAgo' => [
                'input' => time() - 86400,
                'expected' => '1 day ago',
            ],
            'threeDaysAgo' => [
                'input' => time() - (3 * 86400),
                'expected' => '3 days ago',
            ],
            'oneMonthAgo' => [
                'input' => time() - (32 * 86400),
                'expected' => '1 month ago',
            ],
            'oneYearAgo' => [
                'input' => time() - (365 * 86400),
                'expected' => '1 year ago',
            ],
        ];
    }

    /**
     * @return array<string, array{data: array<string, array<string, mixed>>, expected: bool}>
     */
    public static function checkRecordDiff(): array
    {
        return [
            'same' => [
                'data' => [
                    'oldRecord' => ['title' => 'Test Page', 'status' => 1],
                    'newRecord' => ['title' => 'Test Page', 'status' => 1],
                ],
                'expected' => false,
            ],
            'different' => [
                'data' => [
                    'oldRecord' => ['title' => 'Old Title', 'l10n_diffsource' => 'ignored'],
                    'newRecord' => ['title' => 'New Title', 'l10n_diffsource' => 'ignored_too'],
                ],
                'expected' => true,
            ],
            'emptyOldRecord' => [
                'data' => [
                    'oldRecord' => [],
                    'newRecord' => ['title' => 'New Title'],
                ],
                'expected' => false,
            ],
        ];
    }

    /**
     * @return array<string, array{data: mixed, actiontype: int, expected: string}>
     */
    public static function checkCommendDiff(): array
    {
        return [
            'nullData' => [
                'data' => null,
                'actiontype' => 1,
                'expected' => 'Comment action',
            ],
            'emptyData' => [
                'data' => [],
                'actiontype' => 2,
                'expected' => 'Comment action',
            ],
            'noNewRecord' => [
                'data' => ['oldRecord' => ['title' => 'test']],
                'actiontype' => 1,
                'expected' => 'Comment action',
            ],
            'noResolvedDate' => [
                'data' => ['newRecord' => ['title' => 'test']],
                'actiontype' => 1,
                'expected' => 'Comment action',
            ],
            'unresolvedComment' => [
                'data' => ['newRecord' => ['resolved_date' => 0]],
                'actiontype' => 1,
                'expected' => 'Comment action',
            ],
            'resolvedComment' => [
                'data' => ['newRecord' => ['resolved_date' => 1234567890]],
                'actiontype' => 2,
                'expected' => 'Comment action',
            ],
        ];
    }

    /**
     * @return array<string, array{data: array<string, array<string, mixed>>, actiontype: int, expected: bool}>
     */
    public static function checkRecordDiffExtended(): array
    {
        return [
            'fieldSetFromEmpty' => [
                'data' => [
                    'oldRecord' => ['title' => ''],
                    'newRecord' => ['title' => 'New Title'],
                ],
                'actiontype' => 1,
                'expected' => true,
            ],
            'fieldUnset' => [
                'data' => [
                    'oldRecord' => ['title' => 'Old Title'],
                    'newRecord' => ['title' => ''],
                ],
                'actiontype' => 1,
                'expected' => true,
            ],
            'fieldSetFromNull' => [
                'data' => [
                    'oldRecord' => ['title' => null],
                    'newRecord' => ['title' => 'New Title'],
                ],
                'actiontype' => 1,
                'expected' => true,
            ],
            'fieldUnsetToNull' => [
                'data' => [
                    'oldRecord' => ['title' => 'Old Title'],
                    'newRecord' => ['title' => null],
                ],
                'actiontype' => 1,
                'expected' => true,
            ],
            'bothEmpty' => [
                'data' => [
                    'oldRecord' => ['title' => ''],
                    'newRecord' => ['title' => ''],
                ],
                'actiontype' => 1,
                'expected' => false,
            ],
            'bothNull' => [
                'data' => [
                    'oldRecord' => ['title' => null],
                    'newRecord' => ['title' => null],
                ],
                'actiontype' => 1,
                'expected' => false,
            ],
            'skipL10nDiffsource' => [
                'data' => [
                    'oldRecord' => ['l10n_diffsource' => 'old', 'title' => 'Same'],
                    'newRecord' => ['l10n_diffsource' => 'new', 'title' => 'Same'],
                ],
                'actiontype' => 1,
                'expected' => false,
            ],
            'normalFieldChange' => [
                'data' => [
                    'oldRecord' => ['description' => 'Old Description'],
                    'newRecord' => ['description' => 'New Description'],
                ],
                'actiontype' => 1,
                'expected' => true,
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $languageServiceMock = $this->createMock(LanguageService::class);
        $languageServiceMock->method('sL')->willReturnCallback(function (string $key): string {
            return match (true) {
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
                str_contains($key, 'timeAgo.second') => '%d seconds ago',
                str_contains($key, 'history.comment') => 'Comment action',
                str_contains($key, 'history.record.1.set.title') => 'Set %s',
                str_contains($key, 'history.record.1.unset.title') => 'Unset %s',
                str_contains($key, 'history.record.1.change.title') => 'Changed from %s to %s',
                str_contains($key, 'history.record.1.set.description') => 'Set %s',
                str_contains($key, 'history.record.1.unset.description') => 'Unset %s',
                str_contains($key, 'history.record.1.change.description') => 'Changed from %s to %s',
                default => $key,
            };
        });

        $GLOBALS['LANG'] = $languageServiceMock;
    }

    #[DataProvider('timeAgo')]
    #[Test]
    public function testTimeAgo(mixed $input, mixed $expected): void
    {
        self::assertSame($expected, DiffUtility::timeAgo($input));
    }

    #[DataProvider('checkRecordDiff')]
    #[Test]
    public function testCheckRecordDiff(mixed $data, mixed $expected): void
    {
        $result = DiffUtility::checkRecordDiff($data, 1);

        if ($expected === false) {
            self::assertFalse($result);
        } else {
            self::assertIsString($result);
            self::assertNotEmpty($result);
        }
    }

    #[DataProvider('checkCommendDiff')]
    #[Test]
    public function testCheckCommendDiff(mixed $data, mixed $actiontype, mixed $expected): void
    {
        $result = DiffUtility::checkCommendDiff($data, $actiontype);
        self::assertSame($expected, $result);
    }

    #[DataProvider('checkRecordDiffExtended')]
    #[Test]
    public function testCheckRecordDiffExtended(mixed $data, mixed $actiontype, mixed $expected): void
    {
        $result = DiffUtility::checkRecordDiff($data, $actiontype);

        if ($expected === false) {
            self::assertFalse($result);
        } else {
            self::assertIsString($result);
            self::assertNotEmpty($result);
        }
    }
}
