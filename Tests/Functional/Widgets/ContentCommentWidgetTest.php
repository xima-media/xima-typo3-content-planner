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
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Widgets\ContentCommentWidget;
use Xima\XimaTypo3ContentPlanner\Widgets\Provider\ContentCommentDataProvider;

/**
 * ContentCommentWidgetTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentCommentWidgetTest extends AbstractFunctionalTestCase
{
    private WidgetConfigurationInterface $configuration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest('dashboard', ['id' => 1]);

        $this->configuration = $this->createMock(WidgetConfigurationInterface::class);
    }

    #[Test]
    public function renderWidgetContentRendersOpenUndeletedComments(): void
    {
        $this->importCSVDataSet(__DIR__.'/Provider/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Provider/Fixtures/comments.csv');

        $content = $this->createWidget()->renderWidgetContent();

        self::assertStringContainsString('content-planner-comment', $content);
        self::assertStringContainsString('Open comment', $content);
        self::assertStringNotContainsString('Resolved comment', $content);
        self::assertStringNotContainsString('Deleted comment', $content);
        self::assertStringNotContainsString('content-planner-widget__empty-icon', $content);
    }

    #[Test]
    public function renderWidgetContentRendersEmptyStateWhenNoComments(): void
    {
        $content = $this->createWidget()->renderWidgetContent();

        self::assertStringContainsString('content-planner-widget__empty-icon', $content);
        self::assertStringNotContainsString('content-planner-comment__header', $content);
    }

    #[Test]
    public function renderWidgetContentRendersReplyBadgeForReplyComments(): void
    {
        $this->importCSVDataSet(__DIR__.'/Provider/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments_reply.csv');

        $content = $this->createWidget()->renderWidgetContent();

        self::assertStringContainsString('Root comment', $content);
        self::assertStringContainsString('Reply comment', $content);
        self::assertStringContainsString('Reply', $content);
    }

    private function createWidget(): ContentCommentWidget
    {
        return new ContentCommentWidget(
            $this->configuration,
            new ContentCommentDataProvider($this->get(ConnectionPool::class)),
        );
    }
}
