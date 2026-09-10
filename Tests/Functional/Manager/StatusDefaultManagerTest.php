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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Manager;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Manager\StatusDefaultManager;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * StatusDefaultManagerTest.
 *
 * CP-27 (#326): covers the "is_default" uniqueness enforcement - the DataHandlerHook calls
 * StatusDefaultManager::enforceUniqueDefaultAfterSave() once per status record it saves, and
 * this must leave at most one status row with the flag set, deterministically even when a
 * bulk import tries to set it on two rows within the same process_datamap() call.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class StatusDefaultManagerTest extends AbstractFunctionalTestCase
{
    private StatusDefaultManager $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status_default.csv');
        $this->loginBackendUser();
        $this->subject = $this->get(StatusDefaultManager::class);
    }

    #[Test]
    public function enforceUniqueDefaultAfterSaveDoesNothingWhenIsDefaultFieldIsAbsent(): void
    {
        $this->subject->enforceUniqueDefaultAfterSave(2, ['title' => 'In Progress'], $this->createDataHandler());

        self::assertSame(1, $this->fetchIsDefault(1));
    }

    #[Test]
    public function enforceUniqueDefaultAfterSaveDoesNothingWhenIsDefaultIsZero(): void
    {
        $this->subject->enforceUniqueDefaultAfterSave(2, [Configuration::FIELD_STATUS_IS_DEFAULT => 0], $this->createDataHandler());

        self::assertSame(1, $this->fetchIsDefault(1));
    }

    #[Test]
    public function enforceUniqueDefaultAfterSaveClearsOtherRowsWhenAnExistingRecordTurnsTheFlagOn(): void
    {
        // Simulate the DataHandler having just persisted uid 2 with is_default = 1 - the
        // hook runs afterDatabaseOperations, i.e. after that write already happened.
        $this->getConnectionPool()->getConnectionForTable(Configuration::TABLE_STATUS)
            ->update(Configuration::TABLE_STATUS, [Configuration::FIELD_STATUS_IS_DEFAULT => 1], ['uid' => 2])
        ;

        $this->subject->enforceUniqueDefaultAfterSave(2, [Configuration::FIELD_STATUS_IS_DEFAULT => 1], $this->createDataHandler());

        self::assertSame(0, $this->fetchIsDefault(1));
        self::assertSame(1, $this->fetchIsDefault(2));
        self::assertSame(0, $this->fetchIsDefault(3));
    }

    #[Test]
    public function enforceUniqueDefaultAfterSaveResolvesNewRecordIdsViaSubstNEWwithIDs(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable(Configuration::TABLE_STATUS);
        $connection->insert(Configuration::TABLE_STATUS, [
            'title' => 'Backlog',
            'icon' => 'flag',
            'color' => 'gray',
            Configuration::FIELD_STATUS_IS_DEFAULT => 1,
        ]);
        $newUid = (int) $connection->lastInsertId();

        $dataHandler = $this->createDataHandler();
        $dataHandler->substNEWwithIDs['NEW123abc'] = $newUid;

        $this->subject->enforceUniqueDefaultAfterSave('NEW123abc', [Configuration::FIELD_STATUS_IS_DEFAULT => 1], $dataHandler);

        self::assertSame(0, $this->fetchIsDefault(1));
        self::assertSame(1, $this->fetchIsDefault($newUid));
    }

    /**
     * Bulk import scenario from the acceptance criteria: two new status records, both with
     * is_default = 1, saved within the same process_datamap() call. Each row's own
     * afterDatabaseOperations run clears every other row again, so the row processed last
     * (record B here) is deterministically the one left with the flag.
     */
    #[Test]
    public function bulkImportSettingTwoDefaultsLeavesOnlyTheLastProcessedRecordAsDefault(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([
            Configuration::TABLE_STATUS => [
                'NEWrecordA' => [
                    'pid' => 0,
                    'title' => 'Record A',
                    'icon' => 'flag',
                    'color' => 'blue',
                    Configuration::FIELD_STATUS_IS_DEFAULT => 1,
                ],
                'NEWrecordB' => [
                    'pid' => 0,
                    'title' => 'Record B',
                    'icon' => 'flag',
                    'color' => 'red',
                    Configuration::FIELD_STATUS_IS_DEFAULT => 1,
                ],
            ],
        ], []);
        $dataHandler->process_datamap();

        self::assertSame([], $dataHandler->errorLog);

        $uidA = (int) $dataHandler->substNEWwithIDs['NEWrecordA'];
        $uidB = (int) $dataHandler->substNEWwithIDs['NEWrecordB'];

        // The pre-existing default from the fixture (uid 1) must also be cleared.
        self::assertSame(0, $this->fetchIsDefault(1));
        self::assertSame(0, $this->fetchIsDefault($uidA));
        self::assertSame(1, $this->fetchIsDefault($uidB));
    }

    private function createDataHandler(): DataHandler
    {
        return GeneralUtility::makeInstance(DataHandler::class);
    }

    private function fetchIsDefault(int $uid): int
    {
        return (int) $this->getConnectionPool()->getConnectionForTable(Configuration::TABLE_STATUS)
            ->select([Configuration::FIELD_STATUS_IS_DEFAULT], Configuration::TABLE_STATUS, ['uid' => $uid])
            ->fetchOne()
        ;
    }
}
