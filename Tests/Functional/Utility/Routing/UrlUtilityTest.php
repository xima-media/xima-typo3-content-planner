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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Utility\Routing;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;
use Xima\XimaTypo3ContentPlanner\Utility\Routing\UrlUtility;

/**
 * UrlUtilityTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class UrlUtilityTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest('web_layout', ['id' => 1]);
    }

    #[Test]
    public function getContentStatusPropertiesEditUrlBuildsRecordEditUrl(): void
    {
        $url = UrlUtility::getContentStatusPropertiesEditUrl('pages', 1);

        $decoded = urldecode($url);
        self::assertStringContainsString('edit[pages][1]', $decoded);
        self::assertStringContainsString('columnsOnly', $decoded);
    }

    #[Test]
    public function getContentStatusPropertiesEditUrlWithoutReturnUrl(): void
    {
        $url = UrlUtility::getContentStatusPropertiesEditUrl('pages', 1, false);

        self::assertStringContainsString('edit', urldecode($url));
    }

    #[Test]
    public function getNewCommentUrlForPageUsesUidAsPid(): void
    {
        $url = UrlUtility::getNewCommentUrl('pages', 1);

        $decoded = urldecode($url);
        self::assertStringContainsString('tx_ximatypo3contentplanner_comment][1]=new', $decoded);
        self::assertStringContainsString('foreign_table', $decoded);
    }

    #[Test]
    public function getNewCommentUrlWithParentCommentUidAddsParentUid(): void
    {
        $url = UrlUtility::getNewCommentUrl('pages', 1, 7);

        self::assertStringContainsString('parent_uid', urldecode($url));
    }

    #[Test]
    public function getEditCommentUrlBuildsRecordEditUrl(): void
    {
        $url = UrlUtility::getEditCommentUrl(5);

        $decoded = urldecode($url);
        self::assertStringContainsString('edit[tx_ximatypo3contentplanner_comment][5]=edit', $decoded);
    }

    #[Test]
    public function getResolvedCommentUrlSetsResolvedDateToZeroWhenResolved(): void
    {
        $url = UrlUtility::getResolvedCommentUrl(5, true);

        $decoded = urldecode($url);
        self::assertStringContainsString('resolved_date]=0', $decoded);
    }

    #[Test]
    public function getResolvedCommentUrlSetsTimestampWhenUnresolved(): void
    {
        $url = UrlUtility::getResolvedCommentUrl(5, false);

        $decoded = urldecode($url);
        self::assertStringContainsString('resolved_date]=', $decoded);
        self::assertStringNotContainsString('resolved_date]=0&', $decoded);
    }

    #[Test]
    public function getDeleteCommentUrlBuildsDeleteCommand(): void
    {
        $url = UrlUtility::getDeleteCommentUrl(5);

        $decoded = urldecode($url);
        self::assertStringContainsString('cmd[tx_ximatypo3contentplanner_comment][5][delete]=1', $decoded);
    }

    #[Test]
    public function getRecordLinkForPagesUsesWebLayout(): void
    {
        $url = UrlUtility::getRecordLink('pages', 1);

        self::assertStringContainsString('id=1', urldecode($url));
    }

    #[Test]
    public function getRecordLinkForDefaultTableUsesRecordEdit(): void
    {
        $url = UrlUtility::getRecordLink('tt_content', 3);

        self::assertStringContainsString('edit[tt_content][3]', urldecode($url));
    }

    #[Test]
    public function getFolderLinkReturnsEmptyStringForNullIdentifier(): void
    {
        self::assertSame('', UrlUtility::getFolderLink(null));
    }

    #[Test]
    public function getFolderLinkReturnsEmptyStringForEmptyIdentifier(): void
    {
        self::assertSame('', UrlUtility::getFolderLink(''));
    }

    #[Test]
    public function getShareUrlBuildsShareRoute(): void
    {
        $url = UrlUtility::getShareUrl('pages', 1);

        $decoded = urldecode($url);
        self::assertStringContainsString('table=pages', $decoded);
        self::assertStringContainsString('uid=1', $decoded);
    }

    #[Test]
    public function getShareUrlIncludesCommentUidWhenProvided(): void
    {
        $url = UrlUtility::getShareUrl('pages', 1, 9);

        self::assertStringContainsString('comment=9', urldecode($url));
    }

    #[Test]
    public function assignToUserBuildsTceDbDataForCurrentUser(): void
    {
        $url = UrlUtility::assignToUser('pages', 1);

        $decoded = urldecode($url);
        self::assertStringContainsString('data[pages][1][tx_ximatypo3contentplanner_assignee]', $decoded);
    }

    #[Test]
    public function assignToUserWithUnassignSetsEmptyAssignee(): void
    {
        $url = UrlUtility::assignToUser('pages', 1, null, true);

        $decoded = urldecode($url);
        self::assertStringContainsString('tx_ximatypo3contentplanner_assignee]=', $decoded);
    }

    #[Test]
    public function assignToUserWithExplicitUserId(): void
    {
        $url = UrlUtility::assignToUser('pages', 1, 42);

        $decoded = urldecode($url);
        self::assertStringContainsString('tx_ximatypo3contentplanner_assignee]=42', $decoded);
    }
}
