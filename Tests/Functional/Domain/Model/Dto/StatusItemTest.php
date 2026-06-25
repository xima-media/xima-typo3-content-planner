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
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\StatusItem;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * StatusItemTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class StatusItemTest extends AbstractFunctionalTestCase
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
    public function createResolvesStatusFromRow(): void
    {
        $item = StatusItem::create($this->pageRow());

        self::assertInstanceOf(StatusItem::class, $item);
        self::assertSame('In Progress', $item->getStatus());
    }

    #[Test]
    public function createWithoutStatusReturnsNullStatus(): void
    {
        $row = $this->pageRow();
        $row['tx_ximatypo3contentplanner_status'] = 0;

        $item = StatusItem::create($row);

        self::assertNull($item->getStatus());
    }

    #[Test]
    public function getTitleReturnsRecordTitle(): void
    {
        $item = StatusItem::create($this->pageRow());

        self::assertSame('Home', $item->getTitle());
    }

    #[Test]
    public function getStatusIconReturnsRenderedIcon(): void
    {
        $item = StatusItem::create($this->pageRow());

        self::assertStringContainsString('<', $item->getStatusIcon());
    }

    #[Test]
    public function getRecordIconReturnsRenderedIcon(): void
    {
        $item = StatusItem::create($this->pageRow());

        self::assertStringContainsString('<', $item->getRecordIcon());
    }

    #[Test]
    public function getRecordLinkReturnsNonEmptyString(): void
    {
        $item = StatusItem::create($this->pageRow());

        self::assertNotSame('', $item->getRecordLink());
    }

    #[Test]
    public function getAssigneeReturnsAssigneeUid(): void
    {
        $item = StatusItem::create($this->pageRow());

        self::assertSame(1, $item->getAssignee());
    }

    #[Test]
    public function getAssigneeNameReturnsBackendUsername(): void
    {
        $item = StatusItem::create($this->pageRow());

        self::assertStringContainsString('admin', $item->getAssigneeName());
    }

    #[Test]
    public function getAssigneeAvatarReturnsString(): void
    {
        $item = StatusItem::create($this->pageRow());

        self::assertIsString($item->getAssigneeAvatar());
    }

    #[Test]
    public function getCommentsHtmlContainsBadgeWhenCommentsPresent(): void
    {
        $item = StatusItem::create($this->pageRow());

        self::assertStringContainsString('badge', $item->getCommentsHtml());
        self::assertStringContainsString('2', $item->getCommentsHtml());
    }

    #[Test]
    public function getCommentsHtmlIsEmptyWithoutComments(): void
    {
        $row = $this->pageRow();
        $row['tx_ximatypo3contentplanner_comments'] = 0;

        $item = StatusItem::create($row);

        self::assertSame('', $item->getCommentsHtml());
    }

    #[Test]
    public function getAssignedToCurrentUserReturnsBool(): void
    {
        $item = StatusItem::create($this->pageRow());

        self::assertIsBool($item->getAssignedToCurrentUser());
    }

    #[Test]
    public function getToDoHtmlReturnsString(): void
    {
        $item = StatusItem::create($this->pageRow());

        self::assertIsString($item->getToDoHtml());
    }

    #[Test]
    public function getToDoTotalCountsComments(): void
    {
        $item = StatusItem::create($this->pageRow());

        self::assertIsInt($item->getToDoTotal());
    }

    #[Test]
    public function getToDoResolvedReturnsInt(): void
    {
        $item = StatusItem::create($this->pageRow());

        self::assertIsInt($item->getToDoResolved());
    }

    #[Test]
    public function getToDoShareUrlReturnsString(): void
    {
        $item = StatusItem::create($this->pageRow());

        self::assertIsString($item->getToDoShareUrl());
    }

    #[Test]
    public function getSiteReturnsNullWithoutMultipleSites(): void
    {
        $item = StatusItem::create($this->pageRow());

        self::assertNull($item->getSite());
    }

    #[Test]
    public function toArrayContainsAllExpectedKeys(): void
    {
        $item = StatusItem::create($this->pageRow());

        $result = $item->toArray();

        self::assertArrayHasKey('data', $result);
        self::assertArrayHasKey('link', $result);
        self::assertArrayHasKey('title', $result);
        self::assertArrayHasKey('status', $result);
        self::assertArrayHasKey('statusIcon', $result);
        self::assertArrayHasKey('recordIcon', $result);
        self::assertArrayHasKey('updated', $result);
        self::assertArrayHasKey('updatedRaw', $result);
        self::assertArrayHasKey('assignee', $result);
        self::assertArrayHasKey('assigneeName', $result);
        self::assertArrayHasKey('assigneeAvatar', $result);
        self::assertArrayHasKey('assignedToCurrentUser', $result);
        self::assertArrayHasKey('comments', $result);
        self::assertArrayHasKey('todo', $result);
        self::assertArrayHasKey('todoShareUrl', $result);
        self::assertArrayHasKey('site', $result);

        self::assertSame('Home', $result['title']);
        self::assertSame('In Progress', $result['status']);
        self::assertSame(1, $result['assignee']);
    }

    #[Test]
    public function toArrayFormatsUpdatedRawTimestamp(): void
    {
        $item = StatusItem::create($this->pageRow());

        $result = $item->toArray();

        self::assertMatchesRegularExpression('/^\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}$/', $result['updatedRaw']);
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
    private function pageRow(): array
    {
        return [
            'uid' => 1,
            'pid' => 0,
            'tablename' => 'pages',
            'title' => 'Home',
            'tstamp' => 1700000000,
            'tx_ximatypo3contentplanner_status' => 2,
            'tx_ximatypo3contentplanner_assignee' => 1,
            'tx_ximatypo3contentplanner_comments' => 2,
        ];
    }
}
