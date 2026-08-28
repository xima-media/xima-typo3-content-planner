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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Domain\Repository;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{Notification, NotificationEventType, NotificationReason};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\NotificationRepository;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * NotificationRepositoryContentChangeTest.
 *
 * Covers {@see NotificationRepository::upsertContentChange()}, the write path behind issue #309's
 * aggregation rule: any number of `content_changed` occurrences for the same (recipient, table,
 * record, calendar day) collapse into a single row with a running counter, until that row is
 * digested - at which point the *next* occurrence must start a brand new row rather than update
 * the digested one.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class NotificationRepositoryContentChangeTest extends AbstractFunctionalTestCase
{
    private NotificationRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->get(NotificationRepository::class);
    }

    #[Test]
    public function fourteenChangesByTwoUsersOnOneRecordInOneDayCollapseToExactlyOneRowPerWatcher(): void
    {
        $actors = [10, 11];
        $day = 1_700_000_000; // fixed instant, well within one calendar day

        for ($i = 0; $i < 14; ++$i) {
            $actorUid = $actors[$i % 2];
            foreach ([2, 3] as $watcherUid) { // two watchers, per-recipient rows are independent
                $this->subject->upsertContentChange($this->contentChangeNotification(
                    recipientUid: $watcherUid,
                    actorUid: $actorUid,
                    crdate: $day + $i,
                ));
            }
        }

        $rowsForWatcherTwo = $this->fetchContentChangeRows(2);
        self::assertCount(1, $rowsForWatcherTwo, 'exactly one notification per watcher, not one per change');
        $payload = json_decode((string) $rowsForWatcherTwo[0]['payload'], true);
        self::assertSame(14, $payload['changeCount']);
        self::assertSame([10, 11], $payload['actorUids']);

        $rowsForWatcherThree = $this->fetchContentChangeRows(3);
        self::assertCount(1, $rowsForWatcherThree);
    }

    #[Test]
    public function aSecondChangeOnTheSameDayUpdatesTheExistingRowInPlaceRatherThanAppending(): void
    {
        $day = 1_700_000_000;
        $this->subject->upsertContentChange($this->contentChangeNotification(2, 10, $day));
        $firstUid = $this->fetchContentChangeRows(2)[0]['uid'];

        $this->subject->upsertContentChange($this->contentChangeNotification(2, 10, $day + 60));

        $rows = $this->fetchContentChangeRows(2);
        self::assertCount(1, $rows);
        self::assertSame($firstUid, $rows[0]['uid'], 'the same row must be updated, not a new one inserted');
        self::assertSame($day + 60, (int) $rows[0]['crdate'], 'crdate should reflect the latest change');
    }

    #[Test]
    public function onceDigestedFurtherChangesCreateANewRowRatherThanReviveTheDigestedOne(): void
    {
        $day = 1_700_000_000;
        $this->subject->upsertContentChange($this->contentChangeNotification(2, 10, $day));
        $digestedUid = (int) $this->fetchContentChangeRows(2)[0]['uid'];
        $this->subject->markDigestedByUids([$digestedUid], 2);

        $this->subject->upsertContentChange($this->contentChangeNotification(2, 10, $day + 60));

        $rows = $this->fetchContentChangeRows(2);
        self::assertCount(2, $rows, 'a change after digestion must create a new row');
        $digestedRow = array_values(array_filter($rows, static fn (array $row): bool => (int) $row['uid'] === $digestedUid))[0];
        self::assertNotNull($digestedRow['digested_at'], 'the already-digested row must be left untouched');
        $newRow = array_values(array_filter($rows, static fn (array $row): bool => (int) $row['uid'] !== $digestedUid))[0];
        self::assertNull($newRow['digested_at']);
        $newPayload = json_decode((string) $newRow['payload'], true);
        self::assertSame(1, $newPayload['changeCount'], 'the new row starts its own counter from one');
    }

    #[Test]
    public function aChangeOnANewCalendarDayStartsANewRowRatherThanExtendingYesterdays(): void
    {
        $todayStart = (int) mktime(0, 0, 0, 6, 15, 2024);
        $yesterdayEvening = $todayStart - 3600;
        $todayMorning = $todayStart + 3600;

        $this->subject->upsertContentChange($this->contentChangeNotification(2, 10, $yesterdayEvening));
        $this->subject->upsertContentChange($this->contentChangeNotification(2, 10, $todayMorning));

        self::assertCount(2, $this->fetchContentChangeRows(2));
    }

    private function contentChangeNotification(int $recipientUid, int $actorUid, int $crdate): Notification
    {
        return new Notification(
            $recipientUid,
            NotificationEventType::ContentChanged,
            'pages',
            1,
            $actorUid,
            NotificationReason::WatchingManually,
            ['version' => 1, 'title' => 'Home', 'changeCount' => 1, 'actorUids' => [$actorUid]],
            $crdate,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchContentChangeRows(int $recipientUid): array
    {
        return $this->getConnectionPool()
            ->getConnectionForTable(Configuration::TABLE_NOTIFICATION)
            ->select(['*'], Configuration::TABLE_NOTIFICATION, [
                'backend_user' => $recipientUid,
                'event_type' => NotificationEventType::ContentChanged->value,
            ])
            ->fetchAllAssociative();
    }
}
