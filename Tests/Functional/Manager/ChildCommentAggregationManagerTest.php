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
 * persisted includeChildComments setting must be on) and the returned comments themselves -
 * flat, so RecordController can merge them into the record's own list, and each flagged as
 * belonging to a foreign record so the view knows to mark it.
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
    public function buildContextReturnsCountEvenWhenIncludeChildCommentsIsDisabled(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/child_comments.csv');

        $context = $this->subject->buildContext('pages', 1, false);

        self::assertFalse($context['active']);
        self::assertSame(2, $context['count']);
    }

    #[Test]
    public function buildContextIsInactiveWhenNoChildRecordHasComments(): void
    {
        $context = $this->subject->buildContext('pages', 1, true);

        self::assertFalse($context['active']);
    }

    #[Test]
    public function buildContextReturnsChildCommentsFlaggedAsForeignRecords(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/child_comments.csv');

        $context = $this->subject->buildContext('pages', 1, true);

        self::assertTrue($context['active']);
        self::assertCount(2, $context['items']);
        self::assertFalse($context['hasMore']);

        foreach ($context['items'] as $item) {
            // Drives the record marker in the Comment partial, which is the only thing telling
            // the reader that this comment is not on the record being viewed.
            self::assertTrue($item->foreignRecord);
            self::assertNotSame('', $item->getRecordLink());
        }
    }

    #[Test]
    public function buildContextAppliesTheTodoFilterToChildComments(): void
    {
        // Fixture child comments carry no todo checklist, so the todo-only filter leaves nothing.
        $this->importCSVDataSet(__DIR__.'/Fixtures/child_comments.csv');

        $context = $this->subject->buildContext('pages', 1, true, false, 'DESC', true);

        self::assertFalse($context['active']);
        self::assertSame(0, $context['count']);
    }
}
