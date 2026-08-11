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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Integration\PagetreeFacets;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Integration\PagetreeFacets\ContentPlannerFacetQuery;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * ContentPlannerFacetQueryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentPlannerFacetQueryTest extends AbstractFunctionalTestCase
{
    private ContentPlannerFacetQuery $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['enableContentElementSupport'] = 1;
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/tt_content.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $this->subject = $this->get(ContentPlannerFacetQuery::class);
    }

    #[Test]
    public function resolveByStatusMatchesPageLevelOnly(): void
    {
        self::assertSame([1], $this->subject->resolveByStatus(['2'], includeContentElements: false));
    }

    #[Test]
    public function resolveByStatusUnionsContentElementLevelWhenIncluded(): void
    {
        $result = $this->subject->resolveByStatus(['2'], includeContentElements: true);
        sort($result);
        // Page 1 matches at page level; page 3 matches only through its content element (uid 10).
        self::assertSame([1, 3], $result);
    }

    #[Test]
    public function resolveByStatusNoneMatchesPagesWithoutStatus(): void
    {
        self::assertSame([3], $this->subject->resolveByStatus(['none'], includeContentElements: false));
    }

    #[Test]
    public function resolveByStatusNeverReturnsDeletedPages(): void
    {
        $result = $this->subject->resolveByStatus(['2'], includeContentElements: false);
        self::assertNotContains(4, $result);
    }

    #[Test]
    public function resolveByStatusNeverReturnsPagesThatAreDeletedEvenViaContentElement(): void
    {
        // Content element uid 12 (pid 4, status 2) sits under page 4, which is
        // itself deleted - a surviving content element under a deleted page
        // must not resurrect that page as a match.
        $result = $this->subject->resolveByStatus(['2'], includeContentElements: true);
        self::assertNotContains(4, $result);
    }

    #[Test]
    public function resolveByStatusReturnsEmptyForNoValues(): void
    {
        self::assertSame([], $this->subject->resolveByStatus([], includeContentElements: false));
    }

    #[Test]
    public function resolveByStatusNoneWithContentElementsExcludesFolderTableAndMatchesLiteralZero(): void
    {
        // enableFilelistSupport pulls sys_file_metadata and the folder table into
        // ExtensionUtility::getRecordTables(); their pid is not a page uid (folder
        // status rows are created with pid=0), so they must not contribute a bogus
        // "page 0" match. tt_content uid 11 (pid 2) has a literal 0 status - "none"
        // must match it the same way it matches NULL.
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['enableFilelistSupport'] = 1;
        $this->importCSVDataSet(__DIR__.'/Fixtures/folders.csv');

        $result = $this->subject->resolveByStatus(['none'], includeContentElements: true);
        sort($result);

        self::assertSame([2, 3], $result);
        self::assertNotContains(0, $result);
    }

    #[Test]
    public function resolveByAssigneeMatchesDirectUid(): void
    {
        self::assertSame([1], $this->subject->resolveByAssignee(['1'], currentUserUid: 0, includeContentElements: false));
    }

    #[Test]
    public function resolveByAssigneeResolvesMeToCurrentUser(): void
    {
        self::assertSame([2], $this->subject->resolveByAssignee(['me'], currentUserUid: 2, includeContentElements: false));
    }

    #[Test]
    public function resolveByAssigneeNoneMatchesUnassignedPages(): void
    {
        self::assertSame([3], $this->subject->resolveByAssignee(['none'], currentUserUid: 0, includeContentElements: false));
    }

    #[Test]
    public function resolveByAssigneeUnionsContentElementLevelWhenIncluded(): void
    {
        // Assignee 3 has no page-level match, only content element uid 10 on page 3.
        self::assertSame([], $this->subject->resolveByAssignee(['3'], currentUserUid: 0, includeContentElements: false));
        self::assertSame([3], $this->subject->resolveByAssignee(['3'], currentUserUid: 0, includeContentElements: true));
    }

    #[Test]
    public function resolveByAssigneeNoneWithContentElementsExcludesFolderTableAndMatchesLiteralZero(): void
    {
        // Same union-safety and 0-means-unset guarantees as the equivalent status
        // test, exercised through resolveByAssignee/FIELD_ASSIGNEE instead.
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['enableFilelistSupport'] = 1;
        $this->importCSVDataSet(__DIR__.'/Fixtures/folders.csv');

        $result = $this->subject->resolveByAssignee(['none'], currentUserUid: 0, includeContentElements: true);
        sort($result);

        self::assertSame([2, 3], $result);
        self::assertNotContains(0, $result);
    }

    #[Test]
    public function resolveByCommentsOpenMatchesUnresolvedComment(): void
    {
        self::assertSame([2], $this->subject->resolveByComments(['open'], currentUserUid: 0, todoEnabled: false, includeContentElements: false));
    }

    #[Test]
    public function resolveByCommentsResolvedMatchesResolvedComment(): void
    {
        self::assertSame([1], $this->subject->resolveByComments(['resolved'], currentUserUid: 0, todoEnabled: false, includeContentElements: false));
    }

    #[Test]
    public function resolveByCommentsMineMatchesCurrentUsersComments(): void
    {
        $result = $this->subject->resolveByComments(['mine'], currentUserUid: 5, todoEnabled: false, includeContentElements: false);
        sort($result);
        self::assertSame([1, 2], $result);
    }

    #[Test]
    public function resolveByCommentsNoneMatchesPagesWithZeroCommentCounter(): void
    {
        self::assertSame([3], $this->subject->resolveByComments(['none'], currentUserUid: 0, todoEnabled: false, includeContentElements: false));
    }

    #[Test]
    public function resolveByCommentsOpenUnionsContentElementLevelWhenIncluded(): void
    {
        $result = $this->subject->resolveByComments(['open'], currentUserUid: 0, todoEnabled: false, includeContentElements: true);
        sort($result);
        // Page 2 (direct open comment) union page 3 (open comment on its content element uid 10).
        self::assertSame([2, 3], $result);
    }

    #[Test]
    public function resolveByCommentsNeverReturnsDeletedPagesViaDirectOrChildComment(): void
    {
        // Comment uid 4 sits directly on deleted page 4 (foreign_table=pages);
        // comment uid 5 sits on content element uid 12 (pid 4), also under the
        // deleted page. Neither must resolve to page 4.
        $result = $this->subject->resolveByComments(['open'], currentUserUid: 0, todoEnabled: false, includeContentElements: true);
        self::assertNotContains(4, $result);
    }

    #[Test]
    public function resolveByCommentsTodoIsIgnoredWhenTodoFeatureDisabled(): void
    {
        self::assertSame([], $this->subject->resolveByComments(['todo'], currentUserUid: 0, todoEnabled: false, includeContentElements: true));
    }

    #[Test]
    public function resolveByCommentsTodoMatchesOpenTodoWhenFeatureEnabled(): void
    {
        self::assertSame([3], $this->subject->resolveByComments(['todo'], currentUserUid: 0, todoEnabled: true, includeContentElements: true));
    }
}
