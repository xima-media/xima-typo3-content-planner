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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Utility\Compatibility;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\RouteUtility;

/**
 * RouteUtilityTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class RouteUtilityTest extends TestCase
{
    #[Test]
    public function getRecordListRouteIdentifierReturnsKnownValue(): void
    {
        self::assertContains(RouteUtility::getRecordListRouteIdentifier(), ['web_list', 'records']);
    }

    #[Test]
    public function isRecordListRouteMatchesBothIdentifiers(): void
    {
        self::assertTrue(RouteUtility::isRecordListRoute('web_list'));
        self::assertTrue(RouteUtility::isRecordListRoute('records'));
        self::assertFalse(RouteUtility::isRecordListRoute('web_layout'));
        self::assertFalse(RouteUtility::isRecordListRoute(''));
    }

    #[Test]
    public function isPageLayoutRoute(): void
    {
        self::assertTrue(RouteUtility::isPageLayoutRoute('web_layout'));
        self::assertFalse(RouteUtility::isPageLayoutRoute('records'));
    }

    #[Test]
    public function isRecordEditRoute(): void
    {
        self::assertTrue(RouteUtility::isRecordEditRoute('record_edit'));
        self::assertFalse(RouteUtility::isRecordEditRoute('web_layout'));
    }

    #[Test]
    public function isContentPlannerSupportedModule(): void
    {
        foreach (['web_layout', 'record_edit', 'web_list', 'records', 'media_management'] as $module) {
            self::assertTrue(RouteUtility::isContentPlannerSupportedModule($module), $module);
        }

        self::assertFalse(RouteUtility::isContentPlannerSupportedModule('dashboard'));
        self::assertFalse(RouteUtility::isContentPlannerSupportedModule(''));
    }

    #[Test]
    public function isReturnUrlRelevantRoute(): void
    {
        foreach (['web_layout', 'web_list', 'records', 'record_edit', 'media_management'] as $route) {
            self::assertTrue(RouteUtility::isReturnUrlRelevantRoute($route), $route);
        }

        self::assertFalse(RouteUtility::isReturnUrlRelevantRoute('dashboard'));
        self::assertFalse(RouteUtility::isReturnUrlRelevantRoute(''));
    }

    #[Test]
    public function isFileListRoute(): void
    {
        self::assertTrue(RouteUtility::isFileListRoute('media_management'));
        self::assertFalse(RouteUtility::isFileListRoute('web_list'));
    }
}
