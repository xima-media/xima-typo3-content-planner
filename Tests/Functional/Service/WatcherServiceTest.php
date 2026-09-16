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

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{WatchMode, WatchSource};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\WatcherRepository;
use Xima\XimaTypo3ContentPlanner\Service\WatcherService;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * WatcherServiceTest.
 *
 * Full sticky-unwatch trigger matrix (each {@see WatchSource} x each prior {@see WatchMode}),
 * per issue #299's acceptance criteria, against a real database.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class WatcherServiceTest extends AbstractFunctionalTestCase
{
    private WatcherService $subject;
    private WatcherRepository $watcherRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('be_users.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->subject = $this->get(WatcherService::class);
        $this->watcherRepository = $this->get(WatcherRepository::class);
    }

    // ==================== No prior watcher row ====================

    #[Test]
    public function watchWithAssignmentSourceCreatesAutoWatcherWhenNoPriorRow(): void
    {
        $this->subject->watch('pages', 1, 1, WatchSource::Assignment);

        self::assertSame(WatchMode::Auto, $this->watcherRepository->findMode('pages', 1, 1));
    }

    #[Test]
    public function watchWithCommentSourceCreatesAutoWatcherWhenNoPriorRow(): void
    {
        $this->subject->watch('pages', 1, 1, WatchSource::Comment);

        self::assertSame(WatchMode::Auto, $this->watcherRepository->findMode('pages', 1, 1));
    }

    #[Test]
    public function watchWithStatusChangeSourceCreatesAutoWatcherWhenNoPriorRow(): void
    {
        $this->subject->watch('pages', 1, 1, WatchSource::StatusChange);

        self::assertSame(WatchMode::Auto, $this->watcherRepository->findMode('pages', 1, 1));
    }

    #[Test]
    public function watchWithManualSourceCreatesManualWatcherWhenNoPriorRow(): void
    {
        $this->subject->watch('pages', 1, 1, WatchSource::Manual);

        self::assertSame(WatchMode::ManualWatch, $this->watcherRepository->findMode('pages', 1, 1));
    }

    #[Test]
    public function unwatchCreatesManualUnwatchRowWhenNoPriorRow(): void
    {
        $this->subject->unwatch('pages', 1, 1);

        self::assertSame(WatchMode::ManualUnwatch, $this->watcherRepository->findMode('pages', 1, 1));
    }

    // ==================== Prior mode: auto ====================

    #[Test]
    public function autoTriggerUpdatesSourceWhenPriorModeIsAuto(): void
    {
        $this->watcherRepository->upsert('pages', 1, 1, WatchMode::Auto, WatchSource::Assignment);

        $this->subject->watch('pages', 1, 1, WatchSource::Comment);

        self::assertSame(WatchMode::Auto, $this->watcherRepository->findMode('pages', 1, 1));
        $row = $this->watcherRepository->findByRecordAndUser('pages', 1, 1);
        self::assertSame('comment', $row['source']);
    }

    #[Test]
    public function manualWatchUpgradesPriorAutoModeToManualWatch(): void
    {
        $this->watcherRepository->upsert('pages', 1, 1, WatchMode::Auto, WatchSource::Assignment);

        $this->subject->watch('pages', 1, 1, WatchSource::Manual);

        self::assertSame(WatchMode::ManualWatch, $this->watcherRepository->findMode('pages', 1, 1));
    }

    #[Test]
    public function unwatchOverridesPriorAutoMode(): void
    {
        $this->watcherRepository->upsert('pages', 1, 1, WatchMode::Auto, WatchSource::Assignment);

        $this->subject->unwatch('pages', 1, 1);

        self::assertSame(WatchMode::ManualUnwatch, $this->watcherRepository->findMode('pages', 1, 1));
    }

    // ==================== Prior mode: manual_watch (sticky against auto) ====================

    #[Test]
    public function autoTriggerNeverOverridesPriorManualWatch(): void
    {
        $this->watcherRepository->upsert('pages', 1, 1, WatchMode::ManualWatch, WatchSource::Manual);

        $this->subject->watch('pages', 1, 1, WatchSource::Comment);

        $row = $this->watcherRepository->findByRecordAndUser('pages', 1, 1);
        self::assertSame(WatchMode::ManualWatch, $this->watcherRepository->findMode('pages', 1, 1));
        self::assertSame('manual', $row['source'], 'an auto trigger must not even update the source when a manual decision stands');
    }

    #[Test]
    public function explicitManualWatchIsIdempotentWhenAlreadyManualWatch(): void
    {
        $this->watcherRepository->upsert('pages', 1, 1, WatchMode::ManualWatch, WatchSource::Manual);

        $this->subject->watch('pages', 1, 1, WatchSource::Manual);

        self::assertSame(WatchMode::ManualWatch, $this->watcherRepository->findMode('pages', 1, 1));
    }

    #[Test]
    public function unwatchOverridesPriorManualWatch(): void
    {
        $this->watcherRepository->upsert('pages', 1, 1, WatchMode::ManualWatch, WatchSource::Manual);

        $this->subject->unwatch('pages', 1, 1);

        self::assertSame(WatchMode::ManualUnwatch, $this->watcherRepository->findMode('pages', 1, 1));
    }

    // ==================== Prior mode: manual_unwatch (sticky) ====================

    #[Test]
    public function autoAssignmentTriggerNeverReactivatesManualUnwatch(): void
    {
        $this->watcherRepository->upsert('pages', 1, 1, WatchMode::ManualUnwatch, WatchSource::Manual);

        $this->subject->watch('pages', 1, 1, WatchSource::Assignment);

        self::assertSame(WatchMode::ManualUnwatch, $this->watcherRepository->findMode('pages', 1, 1));
    }

    #[Test]
    public function autoCommentTriggerNeverReactivatesManualUnwatch(): void
    {
        $this->watcherRepository->upsert('pages', 1, 1, WatchMode::ManualUnwatch, WatchSource::Manual);

        $this->subject->watch('pages', 1, 1, WatchSource::Comment);

        self::assertSame(WatchMode::ManualUnwatch, $this->watcherRepository->findMode('pages', 1, 1));
    }

    #[Test]
    public function autoStatusChangeTriggerNeverReactivatesManualUnwatch(): void
    {
        $this->watcherRepository->upsert('pages', 1, 1, WatchMode::ManualUnwatch, WatchSource::Manual);

        $this->subject->watch('pages', 1, 1, WatchSource::StatusChange);

        self::assertSame(WatchMode::ManualUnwatch, $this->watcherRepository->findMode('pages', 1, 1));
    }

    #[Test]
    public function explicitManualWatchReactivatesManualUnwatch(): void
    {
        $this->watcherRepository->upsert('pages', 1, 1, WatchMode::ManualUnwatch, WatchSource::Manual);

        $this->subject->watch('pages', 1, 1, WatchSource::Manual);

        self::assertSame(WatchMode::ManualWatch, $this->watcherRepository->findMode('pages', 1, 1));
    }

    #[Test]
    public function unwatchIsIdempotentWhenAlreadyManualUnwatch(): void
    {
        $this->watcherRepository->upsert('pages', 1, 1, WatchMode::ManualUnwatch, WatchSource::Manual);

        $this->subject->unwatch('pages', 1, 1);

        self::assertSame(WatchMode::ManualUnwatch, $this->watcherRepository->findMode('pages', 1, 1));
    }

    // ==================== Upsert semantics / read API ====================

    #[Test]
    public function duplicateTriggerResultsInASingleRow(): void
    {
        $this->subject->watch('pages', 1, 1, WatchSource::Assignment);
        $this->subject->watch('pages', 1, 1, WatchSource::Assignment);
        $this->subject->watch('pages', 1, 1, WatchSource::Comment);

        self::assertSame(1, (int) $this->getConnectionPool()
            ->getConnectionForTable(Configuration::TABLE_WATCHER)
            ->count('*', Configuration::TABLE_WATCHER, []));
    }

    #[Test]
    public function isWatchingReflectsActiveState(): void
    {
        self::assertFalse($this->subject->isWatching('pages', 1, 1));

        $this->subject->watch('pages', 1, 1, WatchSource::Manual);
        self::assertTrue($this->subject->isWatching('pages', 1, 1));

        $this->subject->unwatch('pages', 1, 1);
        self::assertFalse($this->subject->isWatching('pages', 1, 1));
    }

    #[Test]
    public function getActiveWatchersExcludesManuallyUnwatchedUsers(): void
    {
        $this->subject->watch('pages', 1, 1, WatchSource::Assignment);
        $this->subject->watch('pages', 1, 2, WatchSource::Manual);
        $this->subject->unwatch('pages', 1, 2);

        self::assertSame([1], $this->subject->getActiveWatchers('pages', 1));
    }

    #[Test]
    public function getActiveWatchersWithSourceExcludesManuallyUnwatchedUsersAndKeepsSourcePerUser(): void
    {
        $this->subject->watch('pages', 1, 1, WatchSource::Assignment);
        $this->subject->watch('pages', 1, 2, WatchSource::Manual);
        $this->subject->watch('pages', 1, 3, WatchSource::Manual);
        $this->subject->unwatch('pages', 1, 3);

        self::assertEquals(
            [1 => WatchSource::Assignment, 2 => WatchSource::Manual],
            $this->subject->getActiveWatchersWithSource('pages', 1),
        );
    }

    #[Test]
    public function getActiveWatchersWithSourceRejectsAnUnwatchableTable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->subject->getActiveWatchersWithSource('be_users', 1);
    }

    #[Test]
    public function watchRejectsFolderStatusTable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->subject->watch(Configuration::TABLE_FOLDER, 1, 1, WatchSource::Manual);
    }

    #[Test]
    public function watchRejectsSysFileMetadataTable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->subject->watch('sys_file_metadata', 1, 1, WatchSource::Manual);
    }

    #[Test]
    public function watchRejectsATableThatIsNotARegisteredContentPlannerTable(): void
    {
        // be_users carries no content-planner status fields and is never returned by
        // ExtensionUtility::getRecordTables() - watching arbitrary tables outside
        // content-planner-enabled ones is explicitly out of scope for this service.
        $this->expectException(InvalidArgumentException::class);
        $this->subject->watch('be_users', 1, 1, WatchSource::Manual);
    }

    #[Test]
    public function unwatchRejectsAnUnwatchableTable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->subject->unwatch('be_users', 1, 1);
    }

    #[Test]
    public function isWatchingRejectsAnUnwatchableTable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->subject->isWatching('be_users', 1, 1);
    }

    #[Test]
    public function getActiveWatchersRejectsAnUnwatchableTable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->subject->getActiveWatchers('be_users', 1);
    }

    #[Test]
    public function isWatchableIsTrueForARegisteredContentPlannerTable(): void
    {
        self::assertTrue($this->subject->isWatchable('pages'));
    }

    #[Test]
    public function isWatchableIsFalseForAnUnregisteredTable(): void
    {
        self::assertFalse($this->subject->isWatchable('be_users'));
    }
}
