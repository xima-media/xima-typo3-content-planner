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
use Xima\XimaTypo3ContentPlanner\Domain\Model\{WatchMode, WatchSource};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\WatcherRepository;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * WatcherRepositoryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class WatcherRepositoryTest extends AbstractFunctionalTestCase
{
    private WatcherRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('be_users.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->subject = $this->get(WatcherRepository::class);
    }

    #[Test]
    public function findByRecordAndUserReturnsFalseWhenNoneExists(): void
    {
        self::assertFalse($this->subject->findByRecordAndUser('pages', 1, 1));
    }

    #[Test]
    public function findModeReturnsNullWhenNoneExists(): void
    {
        self::assertNull($this->subject->findMode('pages', 1, 1));
    }

    #[Test]
    public function upsertCreatesNewRow(): void
    {
        $this->subject->upsert('pages', 1, 1, WatchMode::Auto, WatchSource::Assignment);

        $row = $this->subject->findByRecordAndUser('pages', 1, 1);
        self::assertIsArray($row);
        self::assertSame('auto', $row['mode']);
        self::assertSame('assignment', $row['source']);
    }

    #[Test]
    public function upsertUpdatesExistingRowInsteadOfDuplicating(): void
    {
        $this->subject->upsert('pages', 1, 1, WatchMode::Auto, WatchSource::Assignment);
        $this->subject->upsert('pages', 1, 1, WatchMode::ManualWatch, WatchSource::Manual);

        self::assertSame(WatchMode::ManualWatch, $this->subject->findMode('pages', 1, 1));
        self::assertSame(1, $this->countWatcherRows());
    }

    #[Test]
    public function findActiveWatcherUserIdsExcludesManualUnwatch(): void
    {
        $this->subject->upsert('pages', 1, 1, WatchMode::Auto, WatchSource::Assignment);
        $this->subject->upsert('pages', 1, 2, WatchMode::ManualUnwatch, WatchSource::Manual);

        self::assertSame([1], $this->subject->findActiveWatcherUserIds('pages', 1));
    }

    #[Test]
    public function findActiveWatchersWithSourceExcludesManualUnwatchAndKeepsSourcePerUser(): void
    {
        $this->subject->upsert('pages', 1, 1, WatchMode::Auto, WatchSource::Assignment);
        $this->subject->upsert('pages', 1, 2, WatchMode::ManualWatch, WatchSource::Manual);
        $this->subject->upsert('pages', 1, 3, WatchMode::ManualUnwatch, WatchSource::Manual);

        self::assertEquals(
            [1 => WatchSource::Assignment, 2 => WatchSource::Manual],
            $this->subject->findActiveWatchersWithSource('pages', 1),
        );
    }

    #[Test]
    public function normalizeToDefaultLanguageUidResolvesTranslationToDefaultLanguageParent(): void
    {
        self::assertSame(1, $this->subject->normalizeToDefaultLanguageUid('pages', 2));
    }

    #[Test]
    public function normalizeToDefaultLanguageUidReturnsSameUidForDefaultLanguageRecord(): void
    {
        self::assertSame(1, $this->subject->normalizeToDefaultLanguageUid('pages', 1));
    }

    #[Test]
    public function normalizeToDefaultLanguageUidReturnsSameUidForNonLocalizableTable(): void
    {
        self::assertSame(1, $this->subject->normalizeToDefaultLanguageUid('be_users', 1));
    }

    private function countWatcherRows(): int
    {
        return (int) $this->getConnectionPool()
            ->getConnectionForTable('tx_ximatypo3contentplanner_watcher')
            ->count('*', 'tx_ximatypo3contentplanner_watcher', []);
    }
}
