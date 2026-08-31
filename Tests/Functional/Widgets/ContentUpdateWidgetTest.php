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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Widgets;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Widgets\ContentUpdateWidget;
use Xima\XimaTypo3ContentPlanner\Widgets\Provider\ContentUpdateDataProvider;

/**
 * ContentUpdateWidgetTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentUpdateWidgetTest extends AbstractFunctionalTestCase
{
    private WidgetConfigurationInterface $configuration;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'] ??= [];
        $this->importSharedDataSet('status.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest('dashboard', ['id' => 1]);

        $this->configuration = $this->createMock(WidgetConfigurationInterface::class);
    }

    #[Test]
    public function renderWidgetContentRendersHistoryItemsWithRelatedRecord(): void
    {
        $this->importCSVDataSet(__DIR__.'/Provider/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/sys_history.csv');

        $content = $this->createWidget()->renderWidgetContent();

        self::assertStringContainsString('content-planner-update-list__item', $content);
        self::assertStringContainsString('Home', $content);
        self::assertStringNotContainsString('content-planner-widget__empty-icon', $content);
    }

    #[Test]
    public function renderWidgetContentRendersEmptyStateWhenNoHistory(): void
    {
        $content = $this->createWidget()->renderWidgetContent();

        self::assertStringContainsString('content-planner-widget__empty-icon', $content);
        self::assertStringNotContainsString('content-planner-update-list__item', $content);
    }

    #[Test]
    public function renderWidgetContentHighlightsRecordsAssignedToCurrentUserWhenFeatureEnabled(): void
    {
        $this->enableExtensionFeature(Configuration::FEATURE_CURRENT_ASSIGNEE_HIGHLIGHT);
        $this->importCSVDataSet(__DIR__.'/Provider/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/sys_history.csv');

        $content = $this->createWidget()->renderWidgetContent();

        self::assertStringContainsString('content-planner-update-list__item--current', $content);
    }

    private function createWidget(): ContentUpdateWidget
    {
        return new ContentUpdateWidget(
            $this->configuration,
            new ContentUpdateDataProvider($this->get(ConnectionPool::class)),
        );
    }
}
