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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Hooks;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Hooks\LiveStatusWriter;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * LiveStatusWriterTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class LiveStatusWriterTest extends AbstractFunctionalTestCase
{
    private LiveStatusWriter $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages_with_status.csv');
        $this->loginBackendUser();
        $this->subject = $this->get(LiveStatusWriter::class);
    }

    #[Test]
    public function outsideAWorkspaceTheDatamapIsLeftAlone(): void
    {
        $dataHandler = $this->createDataHandler(['pages' => [10 => ['title' => 'New', Configuration::FIELD_STATUS => 3]]]);

        $this->subject->defer($dataHandler);

        self::assertSame(['title' => 'New', Configuration::FIELD_STATUS => 3], $dataHandler->datamap['pages'][10]);
    }

    #[Test]
    public function inAWorkspaceTheStatusLeavesTheDatamapSoItDoesNotLandInAVersion(): void
    {
        $GLOBALS['BE_USER']->workspace = 3;
        $dataHandler = $this->createDataHandler(['pages' => [10 => ['title' => 'New', Configuration::FIELD_STATUS => 3]]]);

        $this->subject->defer($dataHandler);

        self::assertSame(['title' => 'New'], $dataHandler->datamap['pages'][10]);
    }

    #[Test]
    public function aDatamapThatOnlyChangesTheStatusIsEmptiedCompletely(): void
    {
        $GLOBALS['BE_USER']->workspace = 3;
        $dataHandler = $this->createDataHandler(['pages' => [10 => [Configuration::FIELD_STATUS => 3]]]);

        $this->subject->defer($dataHandler);

        self::assertSame([], $dataHandler->datamap);
    }

    #[Test]
    public function applyWritesTheDeferredStatusToTheLiveRecord(): void
    {
        $GLOBALS['BE_USER']->workspace = 3;
        $this->subject->defer($this->createDataHandler(['pages' => [10 => [Configuration::FIELD_STATUS => 3]]]));

        $this->subject->apply();

        self::assertSame(3, $this->readLiveStatus(10));
    }

    #[Test]
    public function applyWritesTheDeferredAssigneeToTheLiveRecordToo(): void
    {
        $GLOBALS['BE_USER']->workspace = 3;
        $dataHandler = $this->createDataHandler(['pages' => [10 => ['title' => 'Same', Configuration::FIELD_ASSIGNEE => 2]]]);

        $this->subject->defer($dataHandler);
        $this->subject->apply();

        self::assertArrayNotHasKey(Configuration::FIELD_ASSIGNEE, $dataHandler->datamap['pages'][10]);
        self::assertSame(2, $this->readLive(10, Configuration::FIELD_ASSIGNEE));
    }

    #[Test]
    public function applyGivesAnExistingWorkspaceVersionTheSameStatus(): void
    {
        // The workspaces extension is not part of the test setup, which is what switches versioning on.
        $GLOBALS['TCA']['pages']['ctrl']['versioningWS'] = true;
        $GLOBALS['BE_USER']->workspace = 3;
        $this->subject->defer($this->createDataHandler(['pages' => [10 => [Configuration::FIELD_STATUS => 3]]]));

        $this->subject->apply();

        self::assertSame(3, $this->readLive(10, Configuration::FIELD_STATUS));
        self::assertSame(3, $this->readLive(11, Configuration::FIELD_STATUS), 'the version of page 10 follows the live record');
    }

    #[Test]
    public function applyDoesNothingWhenNothingWasDeferred(): void
    {
        $this->subject->apply();

        self::assertSame(2, $this->readLiveStatus(10));
    }

    #[Test]
    public function aNewRecordKeepsItsStatusBecauseThereIsNoLiveRecordYet(): void
    {
        $GLOBALS['BE_USER']->workspace = 3;
        $dataHandler = $this->createDataHandler(['pages' => ['NEW123' => ['pid' => 0, Configuration::FIELD_STATUS => 3]]]);

        $this->subject->defer($dataHandler);

        self::assertSame(3, $dataHandler->datamap['pages']['NEW123'][Configuration::FIELD_STATUS]);
    }

    #[Test]
    public function aStatusWithoutPermissionToSetItIsNotDeferred(): void
    {
        $this->setUpBackendUser(2);
        $GLOBALS['BE_USER']->workspace = 3;
        $dataHandler = $this->createDataHandler(['pages' => [10 => [Configuration::FIELD_STATUS => 3]]]);

        $this->subject->defer($dataHandler);
        $this->subject->apply();

        self::assertSame(2, $this->readLiveStatus(10));
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $datamap
     */
    private function createDataHandler(array $datamap): DataHandler
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->datamap = $datamap;

        return $dataHandler;
    }

    private function readLiveStatus(int $uid): int
    {
        return $this->readLive($uid, Configuration::FIELD_STATUS);
    }

    private function readLive(int $uid, string $field): int
    {
        return (int) $this->get(ConnectionPool::class)->getConnectionForTable('pages')
            ->select([$field], 'pages', ['uid' => $uid])->fetchOne();
    }
}
