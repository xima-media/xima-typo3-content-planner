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
use TYPO3\CMS\Core\DataHandling\History\RecordHistoryStore;
use TYPO3\CMS\Core\Http\{NormalizedParams, ServerRequest};
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\HistoryItem;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * HistoryItemTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class HistoryItemTest extends AbstractFunctionalTestCase
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
    public function createDecodesRawHistoryData(): void
    {
        $item = HistoryItem::create($this->pageHistoryRow());

        self::assertInstanceOf(HistoryItem::class, $item);
        self::assertIsArray($item->data['raw_history']);
        self::assertArrayHasKey('newRecord', $item->data['raw_history']);
    }

    #[Test]
    public function getPidReturnsRecordUid(): void
    {
        $item = HistoryItem::create($this->pageHistoryRow());

        self::assertSame(1, $item->getPid());
    }

    #[Test]
    public function getRelatedRecordReturnsPage(): void
    {
        $item = HistoryItem::create($this->pageHistoryRow());

        $record = $item->getRelatedRecord();

        self::assertIsArray($record);
        self::assertSame(1, (int) $record['uid']);
    }

    #[Test]
    public function getTitleReturnsPageTitle(): void
    {
        $item = HistoryItem::create($this->pageHistoryRow());
        $item->getRelatedRecord();

        self::assertSame('Home', $item->getTitle());
    }

    #[Test]
    public function getRecordLinkReturnsNonEmptyString(): void
    {
        $item = HistoryItem::create($this->pageHistoryRow());
        $item->getRelatedRecord();

        self::assertNotSame('', $item->getRecordLink());
    }

    #[Test]
    public function getStatusReturnsStatusTitle(): void
    {
        $item = HistoryItem::create($this->pageHistoryRow());
        $item->getRelatedRecord();

        self::assertSame('In Progress', $item->getStatus());
    }

    #[Test]
    public function getStatusIconReturnsRenderedIcon(): void
    {
        $item = HistoryItem::create($this->pageHistoryRow());
        $item->getRelatedRecord();

        self::assertStringContainsString('<', $item->getStatusIcon());
    }

    #[Test]
    public function getRecordIconReturnsRenderedIcon(): void
    {
        $item = HistoryItem::create($this->pageHistoryRow());
        $item->getRelatedRecord();

        self::assertStringContainsString('<', $item->getRecordIcon());
    }

    #[Test]
    public function getTimeAgoReturnsString(): void
    {
        $item = HistoryItem::create($this->pageHistoryRow());

        self::assertNotSame('', $item->getTimeAgo());
    }

    #[Test]
    public function getUserReturnsRealNameAndUsername(): void
    {
        $item = HistoryItem::create($this->pageHistoryRow());

        self::assertSame('Administrator (admin)', $item->getUser());
    }

    #[Test]
    public function getUserReturnsUsernameOnlyWhenNoRealName(): void
    {
        $row = $this->pageHistoryRow();
        $row['realName'] = '';

        $item = HistoryItem::create($row);

        self::assertSame('admin', $item->getUser());
    }

    #[Test]
    public function getChangeTypeIconForStatusChangeReturnsIcon(): void
    {
        $item = HistoryItem::create($this->pageHistoryRow());

        self::assertStringContainsString('<', $item->getChangeTypeIcon());
    }

    #[Test]
    public function getChangeTypeIconForAssigneeChangeReturnsIcon(): void
    {
        $row = $this->pageHistoryRow();
        $row['history_data'] = json_encode([
            'newRecord' => [Configuration::FIELD_ASSIGNEE => 1],
        ]);

        $item = HistoryItem::create($row);

        self::assertStringContainsString('<', $item->getChangeTypeIcon());
    }

    #[Test]
    public function getChangeTypeIconForCommentReturnsIcon(): void
    {
        $item = HistoryItem::create($this->commentHistoryRow());

        self::assertStringContainsString('<', $item->getChangeTypeIcon());
    }

    #[Test]
    public function getRawHistoryDataReturnsDecodedArray(): void
    {
        $item = HistoryItem::create($this->pageHistoryRow());

        $raw = $item->getRawHistoryData();

        self::assertIsArray($raw);
        self::assertSame(2, $raw['newRecord'][Configuration::FIELD_STATUS]);
    }

    #[Test]
    public function getRawHistoryDataReturnsNullForEmptyHistory(): void
    {
        $row = $this->pageHistoryRow();
        $row['history_data'] = '';

        $item = HistoryItem::create($row);

        self::assertNull($item->getRawHistoryData());
    }

    #[Test]
    public function getHistoryDataForPageModifyReturnsDiff(): void
    {
        $item = HistoryItem::create($this->pageHistoryRow());

        self::assertNotFalse($item->getHistoryData());
    }

    #[Test]
    public function getHistoryDataForCommentReturnsString(): void
    {
        $item = HistoryItem::create($this->commentHistoryRow());

        self::assertNotFalse($item->getHistoryData());
    }

    #[Test]
    public function getHistoryDataForUnregisteredTableReturnsFalse(): void
    {
        $row = $this->pageHistoryRow();
        $row['tablename'] = 'sys_news';

        $item = HistoryItem::create($row);

        self::assertFalse($item->getHistoryData());
    }

    #[Test]
    public function getAssignedToCurrentUserReturnsBool(): void
    {
        $item = HistoryItem::create($this->pageHistoryRow());

        self::assertIsBool($item->getAssignedToCurrentUser());
    }

    #[Test]
    public function getRelatedRecordForCommentResolvesForeignRecord(): void
    {
        $item = HistoryItem::create($this->commentHistoryRow());

        $record = $item->getRelatedRecord();

        self::assertIsArray($record);
        self::assertSame('pages', $item->data['relatedRecordTablename']);
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
    private function pageHistoryRow(): array
    {
        return [
            'uid' => 100,
            'tablename' => 'pages',
            'recuid' => 1,
            'tstamp' => 1700000000,
            'actiontype' => RecordHistoryStore::ACTION_MODIFY,
            'username' => 'admin',
            'realName' => 'Administrator',
            'history_data' => json_encode([
                'oldRecord' => [Configuration::FIELD_STATUS => 1],
                'newRecord' => [Configuration::FIELD_STATUS => 2],
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commentHistoryRow(): array
    {
        return [
            'uid' => 101,
            'tablename' => Configuration::TABLE_COMMENT,
            'recuid' => 1,
            'tstamp' => 1700000100,
            'actiontype' => RecordHistoryStore::ACTION_ADD,
            'username' => 'editor',
            'realName' => '',
            'history_data' => json_encode([
                'newRecord' => [
                    'foreign_table' => 'pages',
                    'foreign_uid' => 1,
                    'resolved_date' => 0,
                    'parent_uid' => 0,
                ],
                'foreign_table' => 'pages',
                'foreign_uid' => 1,
            ]),
        ];
    }
}
