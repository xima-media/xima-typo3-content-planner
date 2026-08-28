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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Repository;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\FolderStatusRepository;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * FolderStatusRepositoryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class FolderStatusRepositoryTest extends AbstractFunctionalTestCase
{
    private FolderStatusRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/Fixtures/folders.csv');
        $this->subject = $this->get(FolderStatusRepository::class);
    }

    #[Test]
    public function findByCombinedIdentifierReturnsMatchingRecord(): void
    {
        $result = $this->subject->findByCombinedIdentifier('1:/user_upload/');

        self::assertIsArray($result);
        self::assertSame(1, (int) $result['uid']);
        self::assertSame(2, (int) $result['tx_ximatypo3contentplanner_status']);
    }

    #[Test]
    public function findByCombinedIdentifierReturnsFalseForUnknownIdentifier(): void
    {
        self::assertFalse($this->subject->findByCombinedIdentifier('1:/nonexistent/'));
    }

    #[Test]
    public function findByCombinedIdentifierReturnsFalseForInvalidIdentifier(): void
    {
        self::assertFalse($this->subject->findByCombinedIdentifier('no-colon'));
    }

    #[Test]
    public function findByCombinedIdentifierIgnoresDeletedRecords(): void
    {
        self::assertFalse($this->subject->findByCombinedIdentifier('1:/deleted_folder/'));
    }

    #[Test]
    public function findByCombinedIdentifiersBatchResolvesMultipleFolders(): void
    {
        $result = $this->subject->findByCombinedIdentifiers([
            '1:/user_upload/',
            '1:/user_upload/sub/',
            '1:/deleted_folder/',
            '1:/nonexistent/',
        ]);

        self::assertArrayHasKey('1:/user_upload/', $result);
        self::assertArrayHasKey('1:/user_upload/sub/', $result);
        self::assertArrayNotHasKey('1:/deleted_folder/', $result);
        self::assertArrayNotHasKey('1:/nonexistent/', $result);
        self::assertSame(2, (int) $result['1:/user_upload/']['tx_ximatypo3contentplanner_status']);
        self::assertSame(3, (int) $result['1:/user_upload/sub/']['tx_ximatypo3contentplanner_status']);
    }

    #[Test]
    public function findByCombinedIdentifiersReturnsEmptyArrayForNoInput(): void
    {
        self::assertSame([], $this->subject->findByCombinedIdentifiers([]));
    }

    #[Test]
    public function findByUidReturnsMatchingRecord(): void
    {
        $result = $this->subject->findByUid(2);

        self::assertIsArray($result);
        self::assertSame('/user_upload/sub/', $result['folder_identifier']);
    }

    #[Test]
    public function findByUidReturnsFalseForUnknownUid(): void
    {
        self::assertFalse($this->subject->findByUid(999));
    }

    #[Test]
    public function findByUidIgnoresDeletedRecords(): void
    {
        self::assertFalse($this->subject->findByUid(3));
    }

    #[Test]
    public function createInsertsNewRecordAndReturnsUid(): void
    {
        $uid = $this->subject->create('1:/new_folder/', 2, 1);

        self::assertGreaterThan(0, $uid);
        $record = $this->subject->findByUid($uid);
        self::assertIsArray($record);
        self::assertSame('/new_folder/', $record['folder_identifier']);
        self::assertSame(2, (int) $record['tx_ximatypo3contentplanner_status']);
        self::assertSame(1, (int) $record['tx_ximatypo3contentplanner_assignee']);
    }

    #[Test]
    public function createThrowsForInvalidCombinedIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->subject->create('invalid', 2, null);
    }

    #[Test]
    public function createOrUpdateUpdatesExistingRecord(): void
    {
        $uid = $this->subject->createOrUpdate('1:/user_upload/', 3, 5);

        self::assertSame(1, $uid);
        $record = $this->subject->findByUid(1);
        self::assertIsArray($record);
        self::assertSame(3, (int) $record['tx_ximatypo3contentplanner_status']);
        self::assertSame(5, (int) $record['tx_ximatypo3contentplanner_assignee']);
    }

    #[Test]
    public function createOrUpdateCreatesWhenNotExisting(): void
    {
        $uid = $this->subject->createOrUpdate('1:/fresh/', 2);

        self::assertGreaterThan(0, $uid);
        self::assertNotSame(1, $uid);
    }

    #[Test]
    public function updateStatusChangesStatusOnly(): void
    {
        $this->subject->updateStatus(1, 1);

        $record = $this->subject->findByUid(1);
        self::assertIsArray($record);
        self::assertSame(1, (int) $record['tx_ximatypo3contentplanner_status']);
        // Assignee unchanged (default false skips set).
        self::assertSame(1, (int) $record['tx_ximatypo3contentplanner_assignee']);
    }

    #[Test]
    public function updateStatusUpdatesAssigneeWhenProvided(): void
    {
        $this->subject->updateStatus(1, 1, 7);

        $record = $this->subject->findByUid(1);
        self::assertIsArray($record);
        self::assertSame(7, (int) $record['tx_ximatypo3contentplanner_assignee']);
    }

    #[Test]
    public function updateCommentsCountSetsCount(): void
    {
        $this->subject->updateCommentsCount(1, 9);

        $record = $this->subject->findByUid(1);
        self::assertIsArray($record);
        self::assertSame(9, (int) $record['tx_ximatypo3contentplanner_comments']);
    }

    #[Test]
    public function getCombinedIdentifierBuildsExpectedString(): void
    {
        self::assertSame('1:/user_upload/', $this->subject->getCombinedIdentifier(1, '/user_upload/'));
    }

    #[Test]
    public function findSubfoldersWithStatusReturnsEmptyForUnknownStorage(): void
    {
        self::assertSame([], $this->subject->findSubfoldersWithStatus('99:/missing/'));
    }

    #[Test]
    public function getAllSubfoldersReturnsEmptyForUnknownStorage(): void
    {
        self::assertSame([], $this->subject->getAllSubfolders('99:/missing/'));
    }

    #[Test]
    public function findByCombinedIdentifierReturnsCachedResultOnSecondCall(): void
    {
        $first = $this->subject->findByCombinedIdentifier('1:/user_upload/');
        $second = $this->subject->findByCombinedIdentifier('1:/user_upload/');

        self::assertSame($first, $second);
    }

    #[Test]
    public function findByCombinedIdentifiersReturnsCachedResultOnSecondCall(): void
    {
        $identifiers = ['1:/user_upload/', '1:/user_upload/sub/'];

        $first = $this->subject->findByCombinedIdentifiers($identifiers);
        $second = $this->subject->findByCombinedIdentifiers($identifiers);

        self::assertSame($first, $second);
    }

    #[Test]
    public function updateStatusInvalidatesCacheForCombinedIdentifier(): void
    {
        $cached = $this->subject->findByCombinedIdentifier('1:/user_upload/');
        self::assertSame(2, (int) $cached['tx_ximatypo3contentplanner_status']);

        $this->subject->updateStatus(1, 4);

        $fresh = $this->subject->findByCombinedIdentifier('1:/user_upload/');
        self::assertSame(4, (int) $fresh['tx_ximatypo3contentplanner_status']);
    }

    #[Test]
    public function updateCommentsCountInvalidatesCacheForCombinedIdentifier(): void
    {
        $this->subject->findByCombinedIdentifier('1:/user_upload/');

        $this->subject->updateCommentsCount(1, 42);

        $fresh = $this->subject->findByCombinedIdentifier('1:/user_upload/');
        self::assertSame(42, (int) $fresh['tx_ximatypo3contentplanner_comments']);
    }

    #[Test]
    public function createOrUpdateInvalidatesCacheForCombinedIdentifier(): void
    {
        $this->subject->findByCombinedIdentifier('1:/user_upload/');

        $this->subject->createOrUpdate('1:/user_upload/', 4, 9);

        $fresh = $this->subject->findByCombinedIdentifier('1:/user_upload/');
        self::assertSame(4, (int) $fresh['tx_ximatypo3contentplanner_status']);
        self::assertSame(9, (int) $fresh['tx_ximatypo3contentplanner_assignee']);
    }

    #[Test]
    public function writingFolderStatusDoesNotInvalidateCacheOfOtherStorage(): void
    {
        // Folder in storage 2 is cached first...
        $this->subject->create('2:/other_storage/', 1);
        $cachedOther = $this->subject->findByCombinedIdentifier('2:/other_storage/');
        self::assertIsArray($cachedOther);

        // ...then a write happens against storage 1 only.
        $this->subject->updateStatus(1, 4);

        // Bypass the repository and mutate storage 2 directly, so a stale cache read is
        // detectable: if the storage-1 write had (incorrectly) flushed storage 2 as well,
        // the next call below would observe this raw change instead of the cached value.
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_ximatypo3contentplanner_folder');
        $connection->update(
            'tx_ximatypo3contentplanner_folder',
            ['tx_ximatypo3contentplanner_status' => 99],
            ['storage_uid' => 2, 'folder_identifier' => '/other_storage/'],
        );

        $stillCached = $this->subject->findByCombinedIdentifier('2:/other_storage/');
        self::assertSame($cachedOther, $stillCached);
    }
}
