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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Manager;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Manager\ChildCommentAggregationManager;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * ChildCommentAggregationManagerTest.
 *
 * CP-29 (#328): covers the "should aggregation run at all" gate (table must be pages, the
 * persisted includeChildComments setting must be on) and the grouping of the returned comments
 * by their child record (one group per record, with the type icon/title/deep link CommentItem
 * already exposes).
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ChildCommentAggregationManagerTest extends AbstractFunctionalTestCase
{
    private ChildCommentAggregationManager $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->enableExtensionFeature('registerAdditionalRecordTables', ['tt_content']);
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->subject = $this->get(ChildCommentAggregationManager::class);
    }

    #[Test]
    public function buildContextIsInactiveForNonPageTables(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/child_comments.csv');

        $context = $this->subject->buildContext('tt_content', 1, true);

        self::assertFalse($context['active']);
    }

    #[Test]
    public function buildContextIsInactiveWhenIncludeChildCommentsIsDisabled(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/child_comments.csv');

        $context = $this->subject->buildContext('pages', 1, false);

        self::assertFalse($context['active']);
    }

    #[Test]
    public function buildContextIsInactiveWhenNoChildRecordHasComments(): void
    {
        $context = $this->subject->buildContext('pages', 1, true);

        self::assertFalse($context['active']);
    }

    #[Test]
    public function buildContextGroupsCommentsByChildRecord(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/child_comments.csv');

        $context = $this->subject->buildContext('pages', 1, true);

        self::assertTrue($context['active']);
        self::assertCount(2, $context['groups']);
        self::assertFalse($context['hasMore']);

        foreach ($context['groups'] as $group) {
            self::assertArrayHasKey('icon', $group);
            self::assertArrayHasKey('title', $group);
            self::assertArrayHasKey('recordLink', $group);
            self::assertNotSame('', $group['recordLink']);
            self::assertCount(1, $group['comments']);
        }
    }
}
