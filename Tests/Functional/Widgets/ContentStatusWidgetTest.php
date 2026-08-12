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
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Widgets\ContentStatusWidget;
use Xima\XimaTypo3ContentPlanner\Widgets\Provider\ContentStatusDataProvider;

/**
 * ContentStatusWidgetTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentStatusWidgetTest extends AbstractFunctionalTestCase
{
    private WidgetConfigurationInterface $configuration;
    private ContentStatusDataProvider $dataProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Provider/Fixtures/pages.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest('dashboard', ['id' => 1]);

        $this->configuration = $this->createMock(WidgetConfigurationInterface::class);
        $this->dataProvider = new ContentStatusDataProvider(
            $this->get(StatusRepository::class),
            $this->get(BackendUserRepository::class),
        );
    }

    #[Test]
    public function renderWidgetContentRendersDefaultStatusModeWithoutFilter(): void
    {
        $content = $this->createWidget()->renderWidgetContent();

        self::assertStringContainsString('content-planner-widget', $content);
        self::assertStringContainsString('<table class="widget-table table">', $content);
        self::assertStringNotContainsString('content-planner-widget--assigned', $content);
        self::assertStringNotContainsString('content-planner-widget--todo', $content);
        self::assertStringNotContainsString('content-planner-widget--has-filter', $content);
        self::assertStringNotContainsString('content-planner-widget__filter-form', $content);
    }

    #[Test]
    public function renderWidgetContentRendersAssigneeMode(): void
    {
        $content = $this->createWidget(['currentUserAssignee' => true])->renderWidgetContent();

        self::assertStringContainsString('content-planner-widget--assigned', $content);
        self::assertStringContainsString('name="currentBackendUser" value="1"', $content);
        self::assertStringContainsString('content planner records assigned to you', $content);
    }

    #[Test]
    public function renderWidgetContentRendersTodoModeWithFeatureDisabled(): void
    {
        $content = $this->createWidget(['todo' => true])->renderWidgetContent();

        self::assertStringContainsString('content-planner-widget--todo', $content);
        self::assertStringContainsString('open tasks in the comments', $content);
        self::assertStringNotContainsString('content-planner-badge', $content);
    }

    #[Test]
    public function renderWidgetContentRendersTodoModeWithFeatureEnabledAndNoTodos(): void
    {
        $this->enableCommentTodosFeature();

        $content = $this->createWidget(['todo' => true])->renderWidgetContent();

        self::assertStringContainsString('content-planner-widget--todo', $content);
        self::assertStringNotContainsString('content-planner-badge', $content);
    }

    #[Test]
    public function renderWidgetContentRendersTodoModeWithPendingTodos(): void
    {
        $this->importCSVDataSet(__DIR__.'/../Repository/Fixtures/comments.csv');
        $this->enableCommentTodosFeature();

        $content = $this->createWidget(['todo' => true])->renderWidgetContent();

        self::assertStringContainsString('data-status="pending"', $content);
        self::assertStringContainsString('1/3', $content);
    }

    #[Test]
    public function renderWidgetContentRendersTodoModeWithAllResolvedTodos(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments_todo_resolved.csv');
        $this->enableCommentTodosFeature();

        $content = $this->createWidget(['todo' => true])->renderWidgetContent();

        self::assertStringContainsString('data-status="resolved"', $content);
        self::assertStringContainsString('2/2', $content);
    }

    #[Test]
    public function renderWidgetContentRendersFilterValuesWithoutTypesWhenSingleRecordTable(): void
    {
        $content = $this->createWidget(['useFilter' => true])->renderWidgetContent();

        self::assertStringContainsString('content-planner-widget--has-filter', $content);
        self::assertStringContainsString('content-planner-widget__filter-form', $content);
        self::assertStringContainsString('<select name="status" id="status"', $content);
        self::assertStringContainsString('<select name="assignee" id="users"', $content);
        self::assertStringNotContainsString('<select name="type" id="type"', $content);
    }

    #[Test]
    public function renderWidgetContentRendersFilterValuesWithTypesWhenMultipleRecordTables(): void
    {
        $this->enableExtensionFeature('registerAdditionalRecordTables', ['tt_content']);

        $content = $this->createWidget(['useFilter' => true])->renderWidgetContent();

        self::assertStringContainsString('<select name="type" id="type"', $content);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createWidget(array $options = []): ContentStatusWidget
    {
        return new ContentStatusWidget($this->configuration, $this->dataProvider, null, [], $options);
    }

    private function enableCommentTodosFeature(): void
    {
        $this->enableExtensionFeature(Configuration::FEATURE_COMMENT_TODOS);
    }
}
