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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Domain\Model\Dto;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\{NormalizedParams, ServerRequest};
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\CommentItem;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * CommentItemTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CommentItemTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comment.csv');
        $this->loginBackendUser();
        $this->initBackendRequest();
    }

    #[Test]
    public function createStoresRowData(): void
    {
        $item = CommentItem::create($this->rootCommentRow());

        self::assertInstanceOf(CommentItem::class, $item);
        self::assertSame('pages', $item->data['foreign_table']);
    }

    #[Test]
    public function getTitleReturnsRelatedRecordTitle(): void
    {
        $item = CommentItem::create($this->rootCommentRow());

        self::assertSame('Home', $item->getTitle());
    }

    #[Test]
    public function getRelatedRecordReturnsPageRecord(): void
    {
        $item = CommentItem::create($this->rootCommentRow());

        $record = $item->getRelatedRecord();

        self::assertIsArray($record);
        self::assertSame(1, (int) $record['uid']);
    }

    #[Test]
    public function getStatusIconReturnsIconIdentifier(): void
    {
        $item = CommentItem::create($this->rootCommentRow());

        self::assertNotSame('', $item->getStatusIcon());
    }

    #[Test]
    public function getRecordIconReturnsIconIdentifier(): void
    {
        $item = CommentItem::create($this->rootCommentRow());

        self::assertNotSame('', $item->getRecordIcon());
    }

    #[Test]
    public function getRecordLinkReturnsNonEmptyString(): void
    {
        $item = CommentItem::create($this->rootCommentRow());

        self::assertNotSame('', $item->getRecordLink());
    }

    #[Test]
    public function getAuthorNameReturnsBackendUsername(): void
    {
        $item = CommentItem::create($this->rootCommentRow());

        self::assertStringContainsString('admin', $item->getAuthorName());
    }

    #[Test]
    public function getTimeAgoReturnsString(): void
    {
        $item = CommentItem::create($this->rootCommentRow());

        self::assertNotSame('', $item->getTimeAgo());
    }

    #[Test]
    public function uriGettersReturnNonEmptyStrings(): void
    {
        $item = CommentItem::create($this->rootCommentRow());

        self::assertNotSame('', $item->getEditUri());
        self::assertNotSame('', $item->getResolvedUri());
        self::assertNotSame('', $item->getDeleteUri());
        self::assertNotSame('', $item->getShareUri());
        self::assertNotSame('', $item->getReplyUri());
    }

    #[Test]
    public function isEditedReflectsEditedFlag(): void
    {
        $row = $this->rootCommentRow();
        $row['edited'] = 1;

        $item = CommentItem::create($row);

        self::assertTrue($item->isEdited());
    }

    #[Test]
    public function isResolvedIsFalseForOpenComment(): void
    {
        $item = CommentItem::create($this->rootCommentRow());

        self::assertFalse($item->isResolved());
    }

    #[Test]
    public function isResolvedIsTrueForResolvedComment(): void
    {
        $item = CommentItem::create($this->resolvedCommentRow());

        self::assertTrue($item->isResolved());
    }

    #[Test]
    public function getResolvedUserReturnsEmptyForOpenComment(): void
    {
        $item = CommentItem::create($this->rootCommentRow());

        self::assertSame('', $item->getResolvedUser());
    }

    #[Test]
    public function getResolvedUserReturnsUsernameForResolvedComment(): void
    {
        $item = CommentItem::create($this->resolvedCommentRow());

        self::assertStringContainsString('admin', $item->getResolvedUser());
    }

    #[Test]
    public function getResolvedDateReturnsTimestamp(): void
    {
        $item = CommentItem::create($this->resolvedCommentRow());

        self::assertSame(1700000300, $item->getResolvedDate());
    }

    #[Test]
    public function permissionGettersReturnBool(): void
    {
        $item = CommentItem::create($this->rootCommentRow());

        self::assertIsBool($item->getCanCurrentUserEdit());
        self::assertIsBool($item->getCanCurrentUserDelete());
        self::assertIsBool($item->getCanCurrentUserResolve());
    }

    #[Test]
    public function getParentUidReturnsParentUid(): void
    {
        $row = $this->rootCommentRow();
        $row['parent_uid'] = 5;

        $item = CommentItem::create($row);

        self::assertSame(5, $item->getParentUid());
    }

    #[Test]
    public function getRepliesAndReplyCountReflectRepliesArray(): void
    {
        $item = CommentItem::create($this->rootCommentRow());
        $reply = CommentItem::create($this->resolvedCommentRow());
        $item->replies = [$reply];

        self::assertCount(1, $item->getReplies());
        self::assertSame(1, $item->getReplyCount());
    }

    #[Test]
    public function getLastReplyTimeAgoIsEmptyWithoutReplies(): void
    {
        $item = CommentItem::create($this->rootCommentRow());

        self::assertSame('', $item->getLastReplyTimeAgo());
    }

    #[Test]
    public function getLastReplyTimeAgoReturnsStringWithReplies(): void
    {
        $item = CommentItem::create($this->rootCommentRow());
        $reply = CommentItem::create($this->resolvedCommentRow());
        $item->replies = [$reply];

        self::assertNotSame('', $item->getLastReplyTimeAgo());
    }

    private function initBackendRequest(): void
    {
        $request = (new ServerRequest('https://example.com/typo3/', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $request = $request->withAttribute(
            'normalizedParams',
            NormalizedParams::createFromServerParams($request->getServerParams()),
        );
        $GLOBALS['TYPO3_REQUEST'] = $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function rootCommentRow(): array
    {
        return [
            'uid' => 1,
            'pid' => 1,
            'foreign_uid' => 1,
            'foreign_table' => 'pages',
            'content' => 'Root comment with a question',
            'author' => 1,
            'edited' => 0,
            'resolved_user' => 0,
            'resolved_date' => 0,
            'parent_uid' => 0,
            'crdate' => 1700000100,
            'tstamp' => 1700000100,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvedCommentRow(): array
    {
        return [
            'uid' => 3,
            'pid' => 1,
            'foreign_uid' => 1,
            'foreign_table' => 'pages',
            'content' => 'Resolved comment',
            'author' => 2,
            'edited' => 0,
            'resolved_user' => 1,
            'resolved_date' => 1700000300,
            'parent_uid' => 0,
            'crdate' => 1700000050,
            'tstamp' => 1700000300,
        ];
    }
}
