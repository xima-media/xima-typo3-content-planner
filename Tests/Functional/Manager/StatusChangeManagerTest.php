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
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Manager\StatusChangeManager;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * StatusChangeManagerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class StatusChangeManagerTest extends AbstractFunctionalTestCase
{
    private StatusChangeManager $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/Fixtures/status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/tt_content.csv');
        $this->loginBackendUser();
        $this->subject = $this->get(StatusChangeManager::class);
    }

    #[Test]
    public function processContentPlannerFieldsDoesNothingWithoutStatusField(): void
    {
        $fields = ['title' => 'unchanged'];
        $this->subject->processContentPlannerFields($fields, 'pages', 1);

        self::assertSame(['title' => 'unchanged'], $fields);
    }

    #[Test]
    public function processContentPlannerFieldsConvertsEmptyStatusToNull(): void
    {
        $fields = [Configuration::FIELD_STATUS => ''];
        $this->subject->processContentPlannerFields($fields, 'pages', 1);

        self::assertNull($fields[Configuration::FIELD_STATUS]);
    }

    #[Test]
    public function processContentPlannerFieldsKeepsStatusForAdmin(): void
    {
        $fields = [Configuration::FIELD_STATUS => 2];
        $this->subject->processContentPlannerFields($fields, 'pages', 1);

        self::assertSame(2, $fields[Configuration::FIELD_STATUS]);
    }

    #[Test]
    public function processContentPlannerFieldsResetClearsAssignee(): void
    {
        $fields = [Configuration::FIELD_STATUS => '', Configuration::FIELD_ASSIGNEE => 5];
        $this->subject->processContentPlannerFields($fields, 'pages', 2);

        self::assertNull($fields[Configuration::FIELD_STATUS]);
        self::assertNull($fields[Configuration::FIELD_ASSIGNEE]);
    }

    #[Test]
    public function processContentPlannerFieldsReturnsEarlyForUnknownRecord(): void
    {
        $fields = [Configuration::FIELD_STATUS => 2];
        $this->subject->processContentPlannerFields($fields, 'pages', 999);

        // No record found, but the status field is still normalised/kept.
        self::assertSame(2, $fields[Configuration::FIELD_STATUS]);
    }

    #[Test]
    public function clearStatusOfExtensionRecordsClearsAllStatusForTable(): void
    {
        $this->subject->clearStatusOfExtensionRecords('tt_content');

        $rows = $this->getConnectionPool()
            ->getConnectionForTable('tt_content')
            ->select(['uid', Configuration::FIELD_STATUS], 'tt_content')
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            self::assertNull($row[Configuration::FIELD_STATUS]);
        }
    }

    #[Test]
    public function clearStatusOfExtensionRecordsClearsOnlyMatchingPid(): void
    {
        $this->subject->clearStatusOfExtensionRecords('tt_content', pid: 2);

        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $statusUid1 = $connection->select(['tx_ximatypo3contentplanner_status'], 'tt_content', ['uid' => 1])
            ->fetchOne();

        self::assertNull($statusUid1);
    }

    #[Test]
    public function clearStatusOfExtensionRecordsClearsOnlyMatchingStatus(): void
    {
        $this->subject->clearStatusOfExtensionRecords('tt_content', status: 1);

        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $statusUid2 = $connection->select(['tx_ximatypo3contentplanner_status'], 'tt_content', ['uid' => 2])
            ->fetchOne();

        // Record 2 had status 2, which does not match the filter, so it stays.
        self::assertSame(2, (int) $statusUid2);
    }
}
