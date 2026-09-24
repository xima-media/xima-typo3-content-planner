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
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\PaginatedResult;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, RecordRepository, StatusRepository};
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
        // findAllByFilter() builds raw UNION SQL invalid on the functional suite's SQLite backend
        // (see CLAUDE.md), so the repository is mocked here - same convention as
        // RecordModuleControllerTest, which hits the exact same method.
        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findAllByFilter')
            // 999: ContentStatusWidget::ASSIGNEE_COUNT_LIMIT (private, not reachable from here)
            ->with(null, null, 1, null, null, 999)
            ->willReturn(new PaginatedResult([['uid' => 1], ['uid' => 3]], false));

        $content = $this->createWidget(['currentUserAssignee' => true], $recordRepository)->renderWidgetContent();

        self::assertStringContainsString('content-planner-widget--assigned', $content);
        self::assertStringContainsString('name="currentBackendUser" value="1"', $content);
        self::assertStringContainsString('content-planner-kpi-tile', $content);
        self::assertStringContainsString('>2<', $content);
        self::assertStringContainsString('content planner records assigned to you', $content);
    }

    #[Test]
    public function renderWidgetContentRendersAssigneeModeWithNoAssignedRecords(): void
    {
        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findAllByFilter')->willReturn(new PaginatedResult([], false));

        $content = $this->createWidget(['currentUserAssignee' => true], $recordRepository)->renderWidgetContent();

        self::assertStringContainsString('content-planner-kpi-tile--empty', $content);
        self::assertStringContainsString('You have no content planner records assigned to you', $content);
    }

    #[Test]
    public function renderWidgetContentRendersTodoModeWithFeatureDisabled(): void
    {
        $content = $this->createWidget(['todo' => true])->renderWidgetContent();

        self::assertStringContainsString('content-planner-widget--todo', $content);
        self::assertStringContainsString('content-planner-kpi-tile--empty', $content);
        self::assertStringContainsString('There are no open comment tasks', $content);
    }

    #[Test]
    public function renderWidgetContentRendersTodoModeWithFeatureEnabledAndNoTodos(): void
    {
        $this->enableCommentTodosFeature();

        $content = $this->createWidget(['todo' => true])->renderWidgetContent();

        self::assertStringContainsString('content-planner-widget--todo', $content);
        self::assertStringContainsString('content-planner-kpi-tile--empty', $content);
    }

    #[Test]
    public function renderWidgetContentRendersTodoModeWithPendingTodos(): void
    {
        $this->importCSVDataSet(__DIR__.'/../Repository/Fixtures/comments.csv');
        $this->enableCommentTodosFeature();

        $content = $this->createWidget(['todo' => true])->renderWidgetContent();

        self::assertStringContainsString('content-planner-kpi-tile', $content);
        // Global resolved/total across the whole fixture, not just record 10: comment B (uid 2)
        // contributes 1 resolved of 3, plus the unresolved reply on record 30 (uid 8)
        // contributes 0 of 2 - see CommentRepositoryTest::countTodoAllByRecordCountsAllRecordsWhenAllRecordsTrue.
        self::assertStringContainsString('1/5', $content);
    }

    #[Test]
    public function renderWidgetContentRendersTodoModeWithAllResolvedTodos(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments_todo_resolved.csv');
        $this->enableCommentTodosFeature();

        $content = $this->createWidget(['todo' => true])->renderWidgetContent();

        self::assertStringContainsString('content-planner-kpi-tile', $content);
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
    private function createWidget(array $options = [], ?RecordRepository $recordRepository = null): ContentStatusWidget
    {
        return new ContentStatusWidget(
            $this->configuration,
            $this->dataProvider,
            $recordRepository ?? $this->get(RecordRepository::class),
            $this->get(UriBuilder::class),
            null,
            [],
            $options,
        );
    }

    private function enableCommentTodosFeature(): void
    {
        $this->enableExtensionFeature(Configuration::FEATURE_COMMENT_TODOS);
    }
}
