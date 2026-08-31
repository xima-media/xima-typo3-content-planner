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
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Event\{AssigneeChangedEvent, StatusChangeEvent};
use Xima\XimaTypo3ContentPlanner\Manager\{ContentPlannerFieldAuthorizer, StatusChangeManager};
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

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['autoAssignment']);
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['clearCommentsOnStatusReset']);
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['resetContentElementStatusOnPageReset']);
        parent::tearDown();
    }

    #[Test]
    public function processContentPlannerFieldsDoesNothingWithoutStatusField(): void
    {
        $fields = ['title' => 'unchanged'];
        $fields = $this->subject->processContentPlannerFields($fields, 'pages', 1);

        self::assertSame(['title' => 'unchanged'], $fields);
    }

    #[Test]
    public function processContentPlannerFieldsConvertsEmptyStatusToNull(): void
    {
        $fields = [Configuration::FIELD_STATUS => ''];
        $fields = $this->subject->processContentPlannerFields($fields, 'pages', 1);

        self::assertNull($fields[Configuration::FIELD_STATUS]);
    }

    #[Test]
    public function processContentPlannerFieldsKeepsStatusForAdmin(): void
    {
        $fields = [Configuration::FIELD_STATUS => 2];
        $fields = $this->subject->processContentPlannerFields($fields, 'pages', 1);

        self::assertSame(2, $fields[Configuration::FIELD_STATUS]);
    }

    #[Test]
    public function processContentPlannerFieldsResetClearsAssignee(): void
    {
        $fields = [Configuration::FIELD_STATUS => '', Configuration::FIELD_ASSIGNEE => 5];
        $fields = $this->subject->processContentPlannerFields($fields, 'pages', 2);

        self::assertNull($fields[Configuration::FIELD_STATUS]);
        self::assertNull($fields[Configuration::FIELD_ASSIGNEE]);
    }

    #[Test]
    public function processContentPlannerFieldsReturnsEarlyForUnknownRecord(): void
    {
        $fields = [Configuration::FIELD_STATUS => 2];
        $fields = $this->subject->processContentPlannerFields($fields, 'pages', 999);

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

    #[Test]
    public function processContentPlannerFieldsClearsCommentsOnStatusResetWhenFeatureEnabled(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $this->enableFeature('clearCommentsOnStatusReset');

        $fields = [Configuration::FIELD_STATUS => ''];
        $fields = $this->subject->processContentPlannerFields($fields, 'pages', 2);

        $comment = $this->get(CommentRepository::class)->findByUid(1);
        self::assertFalse($comment, 'comment must be soft-deleted when the feature is enabled');
    }

    #[Test]
    public function processContentPlannerFieldsKeepsCommentsOnStatusResetWhenFeatureDisabled(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');

        $fields = [Configuration::FIELD_STATUS => ''];
        $fields = $this->subject->processContentPlannerFields($fields, 'pages', 2);

        $comment = $this->get(CommentRepository::class)->findByUid(1);
        self::assertIsArray($comment, 'comment must survive the reset when the feature is disabled');
    }

    #[Test]
    public function processContentPlannerFieldsAutoAssignsCurrentUserWhenFeatureEnabledAndNoPriorStatus(): void
    {
        $this->enableFeature('autoAssignment');

        // Page 1 has no status/assignee before this change.
        $fields = [Configuration::FIELD_STATUS => 2];
        $fields = $this->subject->processContentPlannerFields($fields, 'pages', 1);

        self::assertSame(1, $fields[Configuration::FIELD_ASSIGNEE]);
    }

    #[Test]
    public function processContentPlannerFieldsDoesNotAutoAssignWhenAssigneeAlreadyProvided(): void
    {
        $this->enableFeature('autoAssignment');

        $fields = [Configuration::FIELD_STATUS => 2, Configuration::FIELD_ASSIGNEE => 9];
        $fields = $this->subject->processContentPlannerFields($fields, 'pages', 1);

        self::assertSame(9, $fields[Configuration::FIELD_ASSIGNEE]);
    }

    #[Test]
    public function processContentPlannerFieldsDoesNotAutoAssignWhenRecordHadStatusBefore(): void
    {
        $this->enableFeature('autoAssignment');

        // Page 2 already had status 1 before this change.
        $fields = [Configuration::FIELD_STATUS => 2];
        $fields = $this->subject->processContentPlannerFields($fields, 'pages', 2);

        self::assertArrayNotHasKey(Configuration::FIELD_ASSIGNEE, $fields);
    }

    #[Test]
    public function processContentPlannerFieldsDoesNotAutoAssignWhenFeatureDisabled(): void
    {
        $fields = [Configuration::FIELD_STATUS => 2];
        $fields = $this->subject->processContentPlannerFields($fields, 'pages', 1);

        self::assertArrayNotHasKey(Configuration::FIELD_ASSIGNEE, $fields);
    }

    #[Test]
    public function processContentPlannerFieldsDispatchesStatusChangeEventWhenStatusChanges(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(StatusChangeEvent::class));
        $manager = new StatusChangeManager(
            $dispatcher,
            $this->get(RecordRepository::class),
            $this->get(CommentRepository::class),
            $this->get(ConnectionPool::class),
            $this->get(ContentPlannerFieldAuthorizer::class),
        );

        // Page 1 has no status before, so setting it to 2 is a real change.
        $fields = [Configuration::FIELD_STATUS => 2];
        $fields = $manager->processContentPlannerFields($fields, 'pages', 1);
    }

    #[Test]
    public function processContentPlannerFieldsDoesNotDispatchEventWhenStatusUnchanged(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects(self::never())->method('dispatch');
        $manager = new StatusChangeManager(
            $dispatcher,
            $this->get(RecordRepository::class),
            $this->get(CommentRepository::class),
            $this->get(ConnectionPool::class),
            $this->get(ContentPlannerFieldAuthorizer::class),
        );

        // Page 2 already has status 1.
        $fields = [Configuration::FIELD_STATUS => 1];
        $fields = $manager->processContentPlannerFields($fields, 'pages', 2);
    }

    #[Test]
    public function processContentPlannerFieldsDispatchesAssigneeChangedEventWhenAssigneeChanges(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(AssigneeChangedEvent::class));
        $manager = new StatusChangeManager(
            $dispatcher,
            $this->get(RecordRepository::class),
            $this->get(CommentRepository::class),
            $this->get(ConnectionPool::class),
            $this->get(ContentPlannerFieldAuthorizer::class),
        );

        // Page 2 already has status 1 (left unchanged here, so the status-change path itself
        // dispatches nothing) and assignee 5; changing the assignee to 9 isolates the
        // assignee-changed dispatch.
        $fields = [Configuration::FIELD_STATUS => 1, Configuration::FIELD_ASSIGNEE => 9];
        $manager->processContentPlannerFields($fields, 'pages', 2);
    }

    #[Test]
    public function processContentPlannerFieldsDispatchesAssigneeChangedEventForAnAssigneeOnlyUpdate(): void
    {
        // Reassigning a record via the assignee modal writes only the assignee field (see
        // UrlUtility::getAssignUri()). Gating this method on the status field alone therefore
        // meant a plain reassignment produced no AssigneeChangedEvent at all - and with it no
        // assignment watcher and no notification.
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(AssigneeChangedEvent::class));
        $manager = new StatusChangeManager(
            $dispatcher,
            $this->get(RecordRepository::class),
            $this->get(CommentRepository::class),
            $this->get(ConnectionPool::class),
            $this->get(ContentPlannerFieldAuthorizer::class),
        );

        // Page 2 has assignee 5; no status key in the payload at all.
        $fields = [Configuration::FIELD_ASSIGNEE => 9];
        $manager->processContentPlannerFields($fields, 'pages', 2);

        self::assertSame(9, $fields[Configuration::FIELD_ASSIGNEE]);
    }

    #[Test]
    public function processContentPlannerFieldsDoesNotDispatchAssigneeChangedEventWhenAssigneeUnchanged(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects(self::never())->method('dispatch');
        $manager = new StatusChangeManager(
            $dispatcher,
            $this->get(RecordRepository::class),
            $this->get(CommentRepository::class),
            $this->get(ConnectionPool::class),
            $this->get(ContentPlannerFieldAuthorizer::class),
        );

        // Page 2 already has assignee 5 and status 1; neither field actually changes.
        $fields = [Configuration::FIELD_STATUS => 1, Configuration::FIELD_ASSIGNEE => 5];
        $manager->processContentPlannerFields($fields, 'pages', 2);
    }

    #[Test]
    public function processContentPlannerFieldsDoesNotDispatchAssigneeChangedEventWhenAssigneeKeyAbsent(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects(self::never())->method('dispatch');
        $manager = new StatusChangeManager(
            $dispatcher,
            $this->get(RecordRepository::class),
            $this->get(CommentRepository::class),
            $this->get(ConnectionPool::class),
            $this->get(ContentPlannerFieldAuthorizer::class),
        );

        // Status unchanged and the assignee field was never part of the incoming payload.
        $fields = [Configuration::FIELD_STATUS => 1];
        $manager->processContentPlannerFields($fields, 'pages', 2);
    }

    #[Test]
    public function processContentPlannerFieldsDispatchesAssigneeChangedEventCoveringAutoAssignment(): void
    {
        $this->enableFeature('autoAssignment');

        /** @var array<int, object> $dispatched */
        $dispatched = [];
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$dispatched) {
                $dispatched[] = $event;

                return $event;
            });
        $manager = new StatusChangeManager(
            $dispatcher,
            $this->get(RecordRepository::class),
            $this->get(CommentRepository::class),
            $this->get(ConnectionPool::class),
            $this->get(ContentPlannerFieldAuthorizer::class),
        );

        // Page 1 has no status/assignee before. Auto-assign only ever fires together with a real
        // status change (from "no status" to a status), so this exercises both events at once:
        // the acceptance criterion is that auto-assignment is also reflected as an
        // AssigneeChangedEvent, not only implicitly via StatusChangeEvent's field array.
        $fields = [Configuration::FIELD_STATUS => 2];
        $manager->processContentPlannerFields($fields, 'pages', 1);

        $assigneeEvents = array_values(array_filter($dispatched, static fn (object $event): bool => $event instanceof AssigneeChangedEvent));
        self::assertCount(1, $assigneeEvents);
        /** @var AssigneeChangedEvent $assigneeEvent */
        $assigneeEvent = $assigneeEvents[0];
        self::assertSame('pages', $assigneeEvent->getTable());
        self::assertSame(1, $assigneeEvent->getUid());
        self::assertNull($assigneeEvent->getPreviousAssignee());
        self::assertSame(1, $assigneeEvent->getNewAssignee());

        self::assertCount(1, array_filter($dispatched, static fn (object $event): bool => $event instanceof StatusChangeEvent));
    }

    #[Test]
    public function statusChangeEventCarriesTheActingBackendUser(): void
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (StatusChangeEvent $event): bool {
                self::assertSame(1, $event->getActorUid());

                return true;
            }));
        $manager = new StatusChangeManager(
            $dispatcher,
            $this->get(RecordRepository::class),
            $this->get(CommentRepository::class),
            $this->get(ConnectionPool::class),
            $this->get(ContentPlannerFieldAuthorizer::class),
        );

        // loginBackendUser() (called in setUp) authenticates as be_users uid 1.
        $fields = [Configuration::FIELD_STATUS => 2];
        $manager->processContentPlannerFields($fields, 'pages', 1);
    }

    #[Test]
    public function processContentPlannerFieldsResetsContentElementStatusOnPageResetWhenFeatureEnabled(): void
    {
        $this->enableFeature('resetContentElementStatusOnPageReset');

        // Page 2 has status 1 before; tt_content uid 1 and 2 (pid 2) carry statuses too.
        $fields = [Configuration::FIELD_STATUS => ''];
        $fields = $this->subject->processContentPlannerFields($fields, 'pages', 2);

        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        self::assertNull($connection->select(['tx_ximatypo3contentplanner_status'], 'tt_content', ['uid' => 1])->fetchOne());
        self::assertNull($connection->select(['tx_ximatypo3contentplanner_status'], 'tt_content', ['uid' => 2])->fetchOne());
    }

    #[Test]
    public function processContentPlannerFieldsKeepsContentElementStatusOnPageResetWhenFeatureDisabled(): void
    {
        $fields = [Configuration::FIELD_STATUS => ''];
        $fields = $this->subject->processContentPlannerFields($fields, 'pages', 2);

        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        self::assertSame(1, (int) $connection->select(['tx_ximatypo3contentplanner_status'], 'tt_content', ['uid' => 1])->fetchOne());
    }

    /**
     * ExtensionConfiguration::get() reads straight from $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'],
     * so toggling a feature for a test is a direct global write (see also ExtensionUtilityTest).
     * Values are reset in tearDown().
     */
    private function enableFeature(string $feature): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY][$feature] = true;
    }
}
