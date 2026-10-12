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
use TYPO3\CMS\Dashboard\Widgets\{ListDataProviderInterface, WidgetConfigurationInterface};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Widgets\ContentKpiWidget;

/**
 * ContentKpiWidgetTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentKpiWidgetTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest('dashboard', ['id' => 1]);
    }

    #[Test]
    public function rendersOneTilePerPresetAndLinksOnlyThoseWithRecords(): void
    {
        $content = $this->createWidget([
            'assignee' => ['count' => 2, 'countLabel' => '2'],
            'todo' => ['count' => 0, 'countLabel' => '0'],
            'openComments' => ['count' => 1000, 'countLabel' => '999+'],
        ])->renderWidgetContent();

        self::assertSame(3, substr_count($content, 'content-planner-kpi-tile__figure'));
        self::assertStringContainsString('content-planner-widget--kpi', $content);
        self::assertStringContainsString('assignee=1', $content);
        self::assertStringContainsString('openComments=1', $content);
        self::assertStringNotContainsString('todo=1', $content);
        self::assertSame(1, substr_count($content, 'content-planner-kpi-tile--empty'));
        self::assertStringContainsString('>999+<', $content);
        self::assertStringNotContainsString('<table', $content);
    }

    /**
     * @param array<string, array{count: int, countLabel: string}> $items
     */
    private function createWidget(array $items): ContentKpiWidget
    {
        $dataProvider = $this->createMock(ListDataProviderInterface::class);
        $dataProvider->method('getItems')->willReturn($items);

        return new ContentKpiWidget(
            $this->createMock(WidgetConfigurationInterface::class),
            $dataProvider,
            $this->get(UriBuilder::class),
        );
    }
}
