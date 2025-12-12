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

namespace Xima\XimaTypo3ContentPlanner\Utility\Compatibility;

use function in_array;

/**
 * RouteUtility.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class RouteUtility
{
    /**
     * Route identifier for the record list module.
     * TYPO3 13: web_list, TYPO3 14: records.
     */
    public static function getRecordListRouteIdentifier(): string
    {
        return VersionUtility::is14OrHigher() ? 'records' : 'web_list';
    }

    /**
     * Check if a route identifier matches the record list module.
     * Accepts both old (web_list) and new (records) identifiers for compatibility.
     */
    public static function isRecordListRoute(string $route): bool
    {
        return in_array($route, ['web_list', 'records'], true);
    }

    /**
     * Check if a route identifier matches the page layout module.
     * The identifier remains 'web_layout' in both TYPO3 13 and 14.
     */
    public static function isPageLayoutRoute(string $route): bool
    {
        return 'web_layout' === $route;
    }

    /**
     * Check if a route identifier matches the record edit route.
     * The identifier remains 'record_edit' in both TYPO3 13 and 14.
     */
    public static function isRecordEditRoute(string $route): bool
    {
        return 'record_edit' === $route;
    }

    /**
     * Check if a module identifier is one of the supported content planner modules.
     * Accepts both old and new identifiers for the record list module.
     */
    public static function isContentPlannerSupportedModule(string $moduleIdentifier): bool
    {
        return in_array($moduleIdentifier, ['web_layout', 'record_edit', 'web_list', 'records', 'media_management'], true);
    }

    /**
     * Check if a route identifier is relevant for generating return URLs.
     * Accepts both old and new identifiers for the record list module.
     */
    public static function isReturnUrlRelevantRoute(string $route): bool
    {
        return in_array($route, ['web_layout', 'web_list', 'records', 'record_edit', 'media_management'], true);
    }

    /**
     * Check if a route identifier matches the file list module.
     * The identifier is 'media_management' in both TYPO3 13 and 14.
     */
    public static function isFileListRoute(string $route): bool
    {
        return 'media_management' === $route;
    }
}
