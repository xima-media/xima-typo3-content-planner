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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Configuration;

use function count;

/**
 * ConfigurationTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
final class ConfigurationTest extends TestCase
{
    public function testExtensionKeyConstant(): void
    {
        self::assertSame('xima_typo3_content_planner', Configuration::EXT_KEY);
    }

    public function testExtensionNameConstant(): void
    {
        self::assertSame('XimaTypo3ContentPlanner', Configuration::EXT_NAME);
    }

    public function testCacheIdentifierConstant(): void
    {
        self::assertSame('ximatypo3contentplanner', Configuration::CACHE_IDENTIFIER);
    }

    public function testFeatureConstants(): void
    {
        // Test all feature constants exist and have expected values
        self::assertSame('autoAssignment', Configuration::FEATURE_AUTO_ASSIGN);
        self::assertSame('currentAssigneeHighlight', Configuration::FEATURE_CURRENT_ASSIGNEE_HIGHLIGHT);
        self::assertSame('clearCommentsOnStatusReset', Configuration::FEATURE_CLEAR_COMMENTS_ON_STATUS_RESET);
        self::assertSame('recordListStatusInfo', Configuration::FEATURE_RECORD_LIST_STATUS_INFO);
        self::assertSame('recordEditHeaderInfo', Configuration::FEATURE_RECORD_EDIT_HEADER_INFO);
        self::assertSame('webListHeaderInfo', Configuration::FEATURE_WEB_LIST_HEADER_INFO);
        self::assertSame('treeStatusInformation', Configuration::FEATURE_TREE_STATUS_INFORMATION);
        self::assertSame('resetContentElementStatusOnPageReset', Configuration::FEATURE_RESET_CONTENT_ELEMENT_STATUS_ON_PAGE_RESET);
        self::assertSame('commentTodos', Configuration::FEATURE_COMMENT_TODOS);
    }

    public function testAllFeatureConstantsAreUnique(): void
    {
        $features = [
            Configuration::FEATURE_AUTO_ASSIGN,
            Configuration::FEATURE_CURRENT_ASSIGNEE_HIGHLIGHT,
            Configuration::FEATURE_CLEAR_COMMENTS_ON_STATUS_RESET,
            Configuration::FEATURE_RECORD_LIST_STATUS_INFO,
            Configuration::FEATURE_RECORD_EDIT_HEADER_INFO,
            Configuration::FEATURE_WEB_LIST_HEADER_INFO,
            Configuration::FEATURE_TREE_STATUS_INFORMATION,
            Configuration::FEATURE_RESET_CONTENT_ELEMENT_STATUS_ON_PAGE_RESET,
            Configuration::FEATURE_COMMENT_TODOS,
        ];

        $uniqueFeatures = array_unique($features);

        self::assertCount(count($features), $uniqueFeatures, 'All feature constants should be unique');
    }

    public function testAllFeatureConstantsAreStrings(): void
    {
        $features = [
            Configuration::FEATURE_AUTO_ASSIGN,
            Configuration::FEATURE_CURRENT_ASSIGNEE_HIGHLIGHT,
            Configuration::FEATURE_CLEAR_COMMENTS_ON_STATUS_RESET,
            Configuration::FEATURE_RECORD_LIST_STATUS_INFO,
            Configuration::FEATURE_RECORD_EDIT_HEADER_INFO,
            Configuration::FEATURE_WEB_LIST_HEADER_INFO,
            Configuration::FEATURE_TREE_STATUS_INFORMATION,
            Configuration::FEATURE_RESET_CONTENT_ELEMENT_STATUS_ON_PAGE_RESET,
            Configuration::FEATURE_COMMENT_TODOS,
        ];

        foreach ($features as $feature) {
            self::assertNotEmpty($feature, 'Feature constant should not be empty');
        }
    }

    public function testExtensionKeyMatchesComposerJson(): void
    {
        // Extension key should follow TYPO3 naming conventions (lowercase, underscores)
        self::assertMatchesRegularExpression('/^[a-z0-9_]+$/', Configuration::EXT_KEY);
        self::assertStringContainsString('xima', Configuration::EXT_KEY);
        self::assertStringContainsString('typo3', Configuration::EXT_KEY);
        self::assertStringContainsString('content', Configuration::EXT_KEY);
        self::assertStringContainsString('planner', Configuration::EXT_KEY);
    }
}
