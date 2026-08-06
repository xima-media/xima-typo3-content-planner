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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\Summary\RecordSummary;
use Xima\XimaTypo3ContentPlanner\Service\RecordSummaryService;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * RecordSummaryServiceTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class RecordSummaryServiceTest extends AbstractFunctionalTestCase
{
    private RecordSummaryService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/summary_pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/summary_comments.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest();
        $this->subject = $this->get(RecordSummaryService::class);
    }

    #[Test]
    public function buildsOneSummaryPerAccessibleRecord(): void
    {
        $summaries = $this->subject->buildForItems([
            ['table' => 'pages', 'uid' => 1],
            ['table' => 'pages', 'uid' => 2],
        ]);

        self::assertCount(2, $summaries);
        self::assertContainsOnlyInstancesOf(RecordSummary::class, $summaries);
    }

    #[Test]
    public function reportsStatusAssigneeAndCounters(): void
    {
        $summary = $this->summaryFor(1);

        self::assertNotNull($summary->status);
        self::assertSame('Draft', $summary->status->title);
        self::assertNotNull($summary->assignee);
        self::assertSame(1, $summary->assignee->uid);
        self::assertSame(2, $summary->comments->total);
    }

    #[Test]
    public function reportsNullStatusAndAssigneeForAnUntouchedRecord(): void
    {
        $summary = $this->summaryFor(3);

        self::assertNull($summary->status);
        self::assertNull($summary->assignee);
        self::assertSame(0, $summary->comments->total);
    }

    #[Test]
    public function serializesWithoutAnyHtmlFragment(): void
    {
        $payload = json_encode(array_map(
            static fn (RecordSummary $summary): array => $summary->toArray(),
            $this->subject->buildForItems([
                ['table' => 'pages', 'uid' => 1],
                ['table' => 'pages', 'uid' => 2],
                ['table' => 'pages', 'uid' => 3],
            ]),
        ));

        self::assertIsString($payload);
        // The whole point of the dedicated DTO: unlike StatusItem::toArray(), nothing
        // here may carry rendered markup or a pre-escaped entity.
        self::assertStringNotContainsString('<', $payload);
        self::assertStringNotContainsString('&amp;', $payload);
        self::assertStringNotContainsString('badge', $payload);
    }

    #[Test]
    public function exposesTheStatusColourAsAHexValueAndTheIconAsAnIdentifier(): void
    {
        $summary = $this->summaryFor(1);

        self::assertNotNull($summary->status);
        self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', (string) $summary->status->colorHex);
        self::assertStringNotContainsString('<', $summary->status->iconIdentifier);
    }

    #[Test]
    public function omitsRecordsOfUnregisteredTables(): void
    {
        self::assertSame([], $this->subject->buildForItems([['table' => 'be_users', 'uid' => 1]]));
    }

    #[Test]
    public function omitsRecordsThatDoNotExist(): void
    {
        self::assertSame([], $this->subject->buildForItems([['table' => 'pages', 'uid' => 9999]]));
    }

    #[Test]
    public function ignoresMalformedItems(): void
    {
        self::assertSame([], $this->subject->buildForItems([
            ['table' => '', 'uid' => 1],
            ['table' => 'pages', 'uid' => 0],
        ]));
    }

    #[Test]
    public function reportsCapabilitiesForEveryRecord(): void
    {
        $capabilities = $this->summaryFor(1)->capabilities;

        self::assertIsBool($capabilities->canChangeStatus);
        self::assertIsBool($capabilities->canUnsetStatus);
        self::assertIsBool($capabilities->canComment);
        // No "may view comments" flag — the extension has no such permission, so it could
        // only ever be true and would imply a check that does not exist.
        self::assertSame(
            ['canChangeStatus', 'canUnsetStatus', 'canComment'],
            array_keys($capabilities->toArray()),
        );
    }

    private function summaryFor(int $uid): RecordSummary
    {
        $summaries = $this->subject->buildForItems([['table' => 'pages', 'uid' => $uid]]);
        self::assertCount(1, $summaries);

        return $summaries[0];
    }
}
