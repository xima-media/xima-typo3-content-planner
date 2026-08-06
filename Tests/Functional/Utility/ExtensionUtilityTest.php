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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Utility;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

/**
 * ExtensionUtilityTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ExtensionUtilityTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser();
    }

    protected function tearDown(): void
    {
        // ExtensionConfiguration reads TYPO3_CONF_VARS live, so flags flipped by a
        // single test would otherwise leak into the following ones.
        unset(
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_FRONTEND_API],
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_EMBEDDABLE_COMMENTS_VIEW],
        );
        parent::tearDown();
    }

    #[Test]
    public function getRecordTablesAlwaysContainsPages(): void
    {
        self::assertContains('pages', ExtensionUtility::getRecordTables());
    }

    #[Test]
    public function getRecordTablesMergesAdditionalTables(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'] = ['tx_news_domain_model_news'];

        self::assertContains('tx_news_domain_model_news', ExtensionUtility::getRecordTables());

        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables']);
    }

    #[Test]
    public function isRegisteredRecordTableReturnsTrueForPages(): void
    {
        self::assertTrue(ExtensionUtility::isRegisteredRecordTable('pages'));
    }

    #[Test]
    public function isRegisteredRecordTableReturnsFalseForUnknownTable(): void
    {
        self::assertFalse(ExtensionUtility::isRegisteredRecordTable('be_users'));
    }

    #[Test]
    public function isFilelistSupportEnabledReturnsBool(): void
    {
        self::assertIsBool(ExtensionUtility::isFilelistSupportEnabled());
    }

    #[Test]
    public function isContentElementSupportEnabledReturnsBool(): void
    {
        self::assertIsBool(ExtensionUtility::isContentElementSupportEnabled());
    }

    #[Test]
    public function isFeatureEnabledReturnsFalseForUnknownFeature(): void
    {
        self::assertFalse(ExtensionUtility::isFeatureEnabled('thisFeatureDoesNotExist'));
    }

    #[Test]
    public function frontendApiIsDisabledByDefault(): void
    {
        self::assertFalse(ExtensionUtility::isFrontendApiEnabled());
    }

    #[Test]
    public function embeddableCommentsViewIsDisabledByDefault(): void
    {
        self::assertFalse(ExtensionUtility::isEmbeddableCommentsViewEnabled());
    }

    #[Test]
    public function frontendApiIsEnabledWhenFlagIsSet(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_FRONTEND_API] = 1;

        self::assertTrue(ExtensionUtility::isFrontendApiEnabled());
    }

    #[Test]
    public function embeddableCommentsViewIsEnabledWhenFlagIsSet(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_EMBEDDABLE_COMMENTS_VIEW] = 1;

        self::assertTrue(ExtensionUtility::isEmbeddableCommentsViewEnabled());
    }

    #[Test]
    public function frontendApiFlagsAreIndependent(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][Configuration::FEATURE_FRONTEND_API] = 1;

        self::assertTrue(ExtensionUtility::isFrontendApiEnabled());
        self::assertFalse(ExtensionUtility::isEmbeddableCommentsViewEnabled());
    }

    #[Test]
    public function getExtensionSettingReturnsEmptyStringForUnknownSetting(): void
    {
        self::assertSame('', ExtensionUtility::getExtensionSetting('thisSettingDoesNotExist'));
    }

    #[Test]
    public function getTitleFieldReturnsTcaLabelForPages(): void
    {
        self::assertSame('title', ExtensionUtility::getTitleField('pages'));
    }

    #[Test]
    public function getTitleReturnsValueWhenKeyPresent(): void
    {
        self::assertSame('My title', ExtensionUtility::getTitle('title', ['title' => 'My title']));
    }

    #[Test]
    public function getTitleReturnsNoRecordTitleWhenRecordFalse(): void
    {
        self::assertNotSame('', ExtensionUtility::getTitle('title', false));
    }

    #[Test]
    public function addContentPlannerTabToTcaAddsColumnsAndPalette(): void
    {
        $GLOBALS['TCA']['tt_content']['palettes']['tx_ximatypo3contentplanner'] = null;

        ExtensionUtility::addContentPlannerTabToTCA('tt_content');

        self::assertArrayHasKey(Configuration::FIELD_STATUS, $GLOBALS['TCA']['tt_content']['columns']);
        self::assertArrayHasKey(Configuration::FIELD_ASSIGNEE, $GLOBALS['TCA']['tt_content']['columns']);
        self::assertArrayHasKey(Configuration::FIELD_COMMENTS, $GLOBALS['TCA']['tt_content']['columns']);
        self::assertArrayHasKey('tx_ximatypo3contentplanner', $GLOBALS['TCA']['tt_content']['palettes']);
    }
}
