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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

class ExtensionUtilityTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset TYPO3_CONF_VARS for clean testing
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['xima_typo3_content_planner']);
    }

    public function testGetRecordTablesReturnsDefaultPages(): void
    {
        $result = ExtensionUtility::getRecordTables();

        self::assertContains('pages', $result);
        self::assertSame(['pages'], $result);
    }

    public function testGetRecordTablesWithAdditionalTables(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['xima_typo3_content_planner']['registerAdditionalRecordTables'] = [
            'tt_content',
            'sys_category',
        ];

        $result = ExtensionUtility::getRecordTables();

        self::assertContains('pages', $result);
        self::assertContains('tt_content', $result);
        self::assertContains('sys_category', $result);
        self::assertSame(['pages', 'tt_content', 'sys_category'], $result);
    }

    public function testIsRegisteredRecordTableWithPages(): void
    {
        self::assertTrue(ExtensionUtility::isRegisteredRecordTable('pages'));
    }

    public function testIsRegisteredRecordTableWithUnknownTable(): void
    {
        self::assertFalse(ExtensionUtility::isRegisteredRecordTable('unknown_table'));
    }

    public function testIsRegisteredRecordTableWithAdditionalTables(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['xima_typo3_content_planner']['registerAdditionalRecordTables'] = [
            'tt_content',
        ];

        self::assertTrue(ExtensionUtility::isRegisteredRecordTable('pages'));
        self::assertTrue(ExtensionUtility::isRegisteredRecordTable('tt_content'));
        self::assertFalse(ExtensionUtility::isRegisteredRecordTable('sys_category'));
    }

    public function testGetTitleField(): void
    {
        $GLOBALS['TCA']['pages']['ctrl']['label'] = 'title';
        $GLOBALS['TCA']['tt_content']['ctrl']['label'] = 'header';

        self::assertSame('title', ExtensionUtility::getTitleField('pages'));
        self::assertSame('header', ExtensionUtility::getTitleField('tt_content'));
    }

    public function testGetTitleWithValidRecord(): void
    {
        $record = ['title' => 'Test Page', 'uid' => 123];

        $result = ExtensionUtility::getTitle('title', $record);

        self::assertSame('Test Page', $result);
    }

    // Skip BackendUtility dependent tests for now - they need TYPO3 backend context

    public function testGetCssTag(): void
    {
        $result = ExtensionUtility::getCssTag(
            'EXT:xima_typo3_content_planner/Resources/Public/Css/test.css',
            ['data-test' => 'value']
        );

        self::assertStringContainsString('<link', $result);
        self::assertStringContainsString('rel="stylesheet"', $result);
        self::assertStringContainsString('media="all"', $result);
        self::assertStringContainsString('data-test="value"', $result);
        self::assertStringContainsString('/>', $result);
    }

    public function testGetJsTag(): void
    {
        $result = ExtensionUtility::getJsTag(
            'EXT:xima_typo3_content_planner/Resources/Public/JavaScript/test.js',
            ['data-module' => 'test']
        );

        self::assertStringContainsString('<script type="module"', $result);
        self::assertStringContainsString('data-module="test"', $result);
        self::assertStringContainsString('</script>', $result);
    }

    // Skip ExtensionConfiguration dependent tests - need proper TYPO3 bootstrap

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }
}
