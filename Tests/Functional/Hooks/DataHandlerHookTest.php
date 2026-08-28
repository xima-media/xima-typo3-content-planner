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
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Event\{CommentCreatedEvent, CommentResolvedEvent};
use Xima\XimaTypo3ContentPlanner\Hooks\DataHandlerHook;
use Xima\XimaTypo3ContentPlanner\Manager\{StatusChangeManager, StatusDefaultManager};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

use function sprintf;

/**
 * DataHandlerHookTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class DataHandlerHookTest extends AbstractFunctionalTestCase
{
    /**
     * The table name is spelled out rather than taken from Configuration::TABLE_STATUS on
     * purpose. This is what the DataHandler actually passes, and the bug being guarded
     * against was precisely that the constant did not match it — feeding the constant in
     * here would compare it with itself and pass no matter what it holds.
     */
    private const STATUS_TABLE = 'tx_ximatypo3contentplanner_domain_model_status';

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginBackendUser();
    }

    #[Test]
    public function preProcessProcessesCommentTodosOnlyWhenHandlingTheCommentRecord(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->datamap = [
            Configuration::TABLE_COMMENT => [
                'NEW123' => ['content' => '<input type="checkbox" checked><input type="checkbox">'],
            ],
        ];
        $hook = $this->createHook();
        $fields = [];

        // Handling an unrelated record (a page) must not touch the comment datamap.
        $hook->processDatamap_preProcessFieldArray($fields, 'pages', 1, $dataHandler);
        self::assertArrayNotHasKey('todo_total', $dataHandler->datamap[Configuration::TABLE_COMMENT]['NEW123']);

        // Handling the comment record processes its to-dos exactly once.
        $hook->processDatamap_preProcessFieldArray($fields, Configuration::TABLE_COMMENT, 'NEW123', $dataHandler);
        self::assertSame(2, $dataHandler->datamap[Configuration::TABLE_COMMENT]['NEW123']['todo_total']);
        self::assertSame(1, $dataHandler->datamap[Configuration::TABLE_COMMENT]['NEW123']['todo_resolved']);
    }

    #[Test]
    public function preProcessResolvesNewCommentEntryEvenWhenCommentIsNotTheFirstDatamapTable(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        // A mixed save (e.g. page + comment created together) where the comment table is
        // not the first key — processDatamap_beforeStart() would skip it in this case.
        $dataHandler->datamap = [
            'pages' => [1 => ['title' => 'Test']],
            Configuration::TABLE_COMMENT => [
                'NEW456' => ['content' => 'a new comment'],
            ],
        ];
        $hook = $this->createHook();
        $fields = [];

        $hook->processDatamap_preProcessFieldArray($fields, 'pages', 1, $dataHandler);
        // fixNewCommentEntry() must still run once the comment record itself is handled,
        // resolving its "NEW..." id despite the non-integer id early return further down.
        $hook->processDatamap_preProcessFieldArray($fields, Configuration::TABLE_COMMENT, 'NEW456', $dataHandler);

        self::assertArrayHasKey('author', $dataHandler->datamap[Configuration::TABLE_COMMENT]['NEW456']);
    }

    #[Test]
    public function deletingAStatusClearsItFromEveryTrackedRecord(): void
    {
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/status_pages.csv');
        self::assertSame(1, $this->statusOfPage(1), 'fixture must start with page 1 on status 1');

        $this->deleteStatus(1);

        // Without this, records keep pointing at a status whose row is gone — the annotation
        // survives its own definition.
        self::assertNull($this->statusOfPage(1), 'deleting a status must clear it from records');
    }

    #[Test]
    public function deletingAStatusIsVisibleToTheNextCachedRead(): void
    {
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/status_pages.csv');
        $recordRepository = $this->get(RecordRepository::class);

        // Warm the listing cache while page 1 still reports status 1.
        self::assertSame(1, $this->cachedStatusOfPage($recordRepository, 1));

        $this->deleteStatusWithRealCache(1);

        // The clearing is a raw write, so without an explicit flush the listing keeps
        // serving a status whose record is gone.
        self::assertNull($this->cachedStatusOfPage($recordRepository, 1), 'findByPid() served a deleted status');
    }

    #[Test]
    public function deletingAStatusLeavesOtherStatusesAlone(): void
    {
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/status_pages.csv');
        self::assertSame(3, $this->statusOfPage(2), 'fixture must start with page 2 on status 3');

        $this->deleteStatus(1);

        // The cleanup is scoped to the deleted status, not a blanket reset.
        self::assertSame(3, $this->statusOfPage(2));
    }

    #[Test]
    public function processDatamapBeforeStartProcessesCommentTodosWhenCommentTableIsFirst(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->datamap = [
            Configuration::TABLE_COMMENT => [
                'NEW789' => ['content' => '<input type="checkbox" checked><input type="checkbox">'],
            ],
        ];

        $this->createHook()->processDatamap_beforeStart($dataHandler);

        self::assertSame(2, $dataHandler->datamap[Configuration::TABLE_COMMENT]['NEW789']['todo_total']);
        self::assertArrayHasKey('author', $dataHandler->datamap[Configuration::TABLE_COMMENT]['NEW789']);
    }

    #[Test]
    public function processDatamapBeforeStartDoesNothingWhenCommentIsNotTheFirstDatamapTable(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->datamap = [
            'pages' => [1 => ['title' => 'Test']],
            Configuration::TABLE_COMMENT => [
                'NEW999' => ['content' => 'irrelevant'],
            ],
        ];

        $this->createHook()->processDatamap_beforeStart($dataHandler);

        self::assertArrayNotHasKey('author', $dataHandler->datamap[Configuration::TABLE_COMMENT]['NEW999']);
    }

    #[Test]
    public function checkCommentResolvedSetsServerSideValuesWhenPermitted(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->datamap = [
            Configuration::TABLE_COMMENT => [
                1 => ['resolved_date' => 999999],
            ],
        ];
        $fields = [];

        // Admin (uid 1, logged in via setUp()) is always permitted to resolve comments.
        $this->createHook()->processDatamap_preProcessFieldArray($fields, Configuration::TABLE_COMMENT, 1, $dataHandler);

        self::assertSame(1, $dataHandler->datamap[Configuration::TABLE_COMMENT][1]['resolved_user']);
        self::assertGreaterThan(0, $dataHandler->datamap[Configuration::TABLE_COMMENT][1]['resolved_date']);
    }

    #[Test]
    public function checkCommentResolvedRemovesFieldsWhenNotPermitted(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $this->setUpBackendUser(2);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->datamap = [
            Configuration::TABLE_COMMENT => [
                1 => ['resolved_date' => 999999],
            ],
        ];
        $fields = [];

        // Editor (uid 2) has no group permissions at all, so resolving must be blocked.
        $this->createHook()->processDatamap_preProcessFieldArray($fields, Configuration::TABLE_COMMENT, 1, $dataHandler);

        self::assertArrayNotHasKey('resolved_date', $dataHandler->datamap[Configuration::TABLE_COMMENT][1]);
        self::assertArrayNotHasKey('resolved_user', $dataHandler->datamap[Configuration::TABLE_COMMENT][1]);
    }

    #[Test]
    public function checkCommentResolvedUnresolvesWhenResolvedDateIsZero(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->datamap = [
            Configuration::TABLE_COMMENT => [
                1 => ['resolved_date' => 0, 'resolved_user' => 0],
            ],
        ];
        $fields = [];

        $this->createHook()->processDatamap_preProcessFieldArray($fields, Configuration::TABLE_COMMENT, 1, $dataHandler);

        self::assertSame(0, $dataHandler->datamap[Configuration::TABLE_COMMENT][1]['resolved_date']);
        self::assertSame(0, $dataHandler->datamap[Configuration::TABLE_COMMENT][1]['resolved_user']);
    }

    #[Test]
    public function checkCommentEditedMarksEditedWhenContentChangedAndPermitted(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->datamap = [
            Configuration::TABLE_COMMENT => [
                1 => ['content' => 'Edited content'],
            ],
        ];
        $fields = [];

        $this->createHook()->processDatamap_preProcessFieldArray($fields, Configuration::TABLE_COMMENT, 1, $dataHandler);

        self::assertSame(1, $dataHandler->datamap[Configuration::TABLE_COMMENT][1]['edited']);
    }

    #[Test]
    public function checkCommentEditedSkipsWhenContentUnchanged(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->datamap = [
            Configuration::TABLE_COMMENT => [
                1 => ['content' => 'Root comment'],
            ],
        ];
        $fields = [];

        $this->createHook()->processDatamap_preProcessFieldArray($fields, Configuration::TABLE_COMMENT, 1, $dataHandler);

        self::assertArrayNotHasKey('edited', $dataHandler->datamap[Configuration::TABLE_COMMENT][1]);
    }

    #[Test]
    public function checkCommentEditedRemovesContentWhenEditingForeignCommentWithoutPermission(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $this->setUpBackendUser(2);
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->datamap = [
            // Comment uid 1 is authored by user 1; editor (uid 2) has no comment-edit-foreign permission.
            Configuration::TABLE_COMMENT => [
                1 => ['content' => 'Hijacked content'],
            ],
        ];
        $fields = [];

        $this->createHook()->processDatamap_preProcessFieldArray($fields, Configuration::TABLE_COMMENT, 1, $dataHandler);

        self::assertArrayNotHasKey('content', $dataHandler->datamap[Configuration::TABLE_COMMENT][1]);
    }

    #[Test]
    public function flattenNestedReplyRedirectsToRootWhenParentIsAlreadyAReply(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->datamap = [
            // Comment uid 2 is itself a reply to root comment uid 1.
            Configuration::TABLE_COMMENT => [
                'NEW1' => ['content' => 'reply to a reply', 'parent_uid' => 2],
            ],
        ];
        $fields = [];

        $this->createHook()->processDatamap_preProcessFieldArray($fields, Configuration::TABLE_COMMENT, 'NEW1', $dataHandler);

        self::assertSame(1, $dataHandler->datamap[Configuration::TABLE_COMMENT]['NEW1']['parent_uid']);
    }

    #[Test]
    public function flattenNestedReplyDemotesToRootWhenParentWasDeleted(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->datamap = [
            // Comment uid 3 is deleted, so findByUid() cannot resolve it.
            Configuration::TABLE_COMMENT => [
                'NEW2' => ['content' => 'orphaned reply', 'parent_uid' => 3],
            ],
        ];
        $fields = [];

        $this->createHook()->processDatamap_preProcessFieldArray($fields, Configuration::TABLE_COMMENT, 'NEW2', $dataHandler);

        self::assertSame(0, $dataHandler->datamap[Configuration::TABLE_COMMENT]['NEW2']['parent_uid']);
    }

    #[Test]
    public function flattenNestedReplyLeavesRootParentUnchanged(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->datamap = [
            // Comment uid 1 is a root comment (parent_uid 0), so no redirection is needed.
            Configuration::TABLE_COMMENT => [
                'NEW3' => ['content' => 'reply to root', 'parent_uid' => 1],
            ],
        ];
        $fields = [];

        $this->createHook()->processDatamap_preProcessFieldArray($fields, Configuration::TABLE_COMMENT, 'NEW3', $dataHandler);

        self::assertSame(1, $dataHandler->datamap[Configuration::TABLE_COMMENT]['NEW3']['parent_uid']);
    }

    #[Test]
    public function fixNewCommentEntrySetsCurrentUserAsAuthorWhenNoneIsGiven(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->datamap = [
            Configuration::TABLE_COMMENT => [
                'NEW4' => ['content' => 'comment from the form', 'parent_uid' => 0],
            ],
        ];
        $fields = [];

        $this->createHook()->processDatamap_preProcessFieldArray($fields, Configuration::TABLE_COMMENT, 'NEW4', $dataHandler);

        self::assertSame(1, $dataHandler->datamap[Configuration::TABLE_COMMENT]['NEW4']['author']);
    }

    #[Test]
    public function fixNewCommentEntryKeepsAuthorProvidedByProgrammaticWrite(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->datamap = [
            Configuration::TABLE_COMMENT => [
                'NEW5' => ['content' => 'comment from the API', 'parent_uid' => 0, 'author' => 2],
            ],
        ];
        $fields = [];

        $this->createHook()->processDatamap_preProcessFieldArray($fields, Configuration::TABLE_COMMENT, 'NEW5', $dataHandler);

        self::assertSame(2, $dataHandler->datamap[Configuration::TABLE_COMMENT]['NEW5']['author']);
    }

    #[Test]
    public function fixNewCommentEntryTreatsAnEmptyAuthorAsNoAttribution(): void
    {
        // isset() would accept these as "an author was provided" and leave the comment authorless.
        foreach ([0, '0', ''] as $index => $emptyAuthor) {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $newId = 'NEW6'.$index;
            $dataHandler->datamap = [
                Configuration::TABLE_COMMENT => [
                    $newId => ['content' => 'comment', 'parent_uid' => 0, 'author' => $emptyAuthor],
                ],
            ];
            $fields = [];

            $this->createHook()->processDatamap_preProcessFieldArray($fields, Configuration::TABLE_COMMENT, $newId, $dataHandler);

            self::assertSame(1, $dataHandler->datamap[Configuration::TABLE_COMMENT][$newId]['author'], sprintf('author %s must fall back to the current user', var_export($emptyAuthor, true)));
        }
    }

    #[Test]
    public function dispatchCommentCreatedEventFallsBackToCurrentUserForEmptyAuthor(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (CommentCreatedEvent $event): bool => 1 === $event->getAuthorUid()));

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->substNEWwithIDs = ['NEW1' => 42];
        $fieldArray = ['foreign_table' => 'pages', 'foreign_uid' => 10, 'author' => 0];

        $this->createHookWithDispatcher($dispatcher)->processDatamap_afterDatabaseOperations('new', Configuration::TABLE_COMMENT, 'NEW1', $fieldArray, $dataHandler);
    }

    #[Test]
    public function dispatchCommentCreatedEventReportsAuthorFromFieldArray(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (CommentCreatedEvent $event): bool => 2 === $event->getAuthorUid()));

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->substNEWwithIDs = ['NEW1' => 42];
        $fieldArray = ['foreign_table' => 'pages', 'foreign_uid' => 10, 'author' => 2];

        $this->createHookWithDispatcher($dispatcher)->processDatamap_afterDatabaseOperations('new', Configuration::TABLE_COMMENT, 'NEW1', $fieldArray, $dataHandler);
    }

    #[Test]
    public function dispatchCommentCreatedEventDispatchesWhenNewCommentIsSaved(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (CommentCreatedEvent $event): bool {
                return 'pages' === $event->getTable()
                    && 10 === $event->getRecordUid()
                    && 42 === $event->getCommentUid()
                    && 1 === $event->getAuthorUid();
            }));

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->substNEWwithIDs = ['NEW1' => 42];
        $fieldArray = ['foreign_table' => 'pages', 'foreign_uid' => 10];

        $this->createHookWithDispatcher($dispatcher)->processDatamap_afterDatabaseOperations('new', Configuration::TABLE_COMMENT, 'NEW1', $fieldArray, $dataHandler);
    }

    #[Test]
    public function dispatchCommentCreatedEventSkipsWhenForeignFieldsAreMissing(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->substNEWwithIDs = ['NEW1' => 42];
        $fieldArray = [];

        $this->createHookWithDispatcher($dispatcher)->processDatamap_afterDatabaseOperations('new', Configuration::TABLE_COMMENT, 'NEW1', $fieldArray, $dataHandler);
    }

    #[Test]
    public function dispatchCommentResolvedEventDispatchesWhenResolvedDateIsSet(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CommentResolvedEvent::class));

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $fieldArray = ['resolved_date' => time()];

        $this->createHookWithDispatcher($dispatcher)->processDatamap_afterDatabaseOperations('update', Configuration::TABLE_COMMENT, 1, $fieldArray, $dataHandler);
    }

    #[Test]
    public function dispatchCommentResolvedEventSkipsWhenResolvedDateIsZero(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $fieldArray = ['resolved_date' => 0];

        $this->createHookWithDispatcher($dispatcher)->processDatamap_afterDatabaseOperations('update', Configuration::TABLE_COMMENT, 1, $fieldArray, $dataHandler);
    }

    #[Test]
    public function updateCommentCountRelationUpdatesCountUsingDirectForeignFields(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $fieldArray = ['foreign_table' => 'pages', 'foreign_uid' => 10];

        $this->createHook()->processDatamap_afterDatabaseOperations('update', Configuration::TABLE_COMMENT, 5, $fieldArray, $dataHandler);

        $record = $this->get(RecordRepository::class)->findByUid('pages', 10);
        self::assertIsArray($record);
        // Comments uid 1 and 2 target page 10 and are open/non-deleted; uid 3 is deleted and excluded.
        self::assertSame(2, (int) $record['tx_ximatypo3contentplanner_comments']);
    }

    #[Test]
    public function updateCommentCountRelationFallsBackToSavedCommentForNewReplies(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->substNEWwithIDs = ['NEW5' => 1];
        $fieldArray = [];

        $this->createHook()->processDatamap_afterDatabaseOperations('new', Configuration::TABLE_COMMENT, 'NEW5', $fieldArray, $dataHandler);

        $record = $this->get(RecordRepository::class)->findByUid('pages', 10);
        self::assertIsArray($record);
        self::assertSame(2, (int) $record['tx_ximatypo3contentplanner_comments']);
    }

    #[Test]
    public function updateCommentCountRelationUpdatesCountOnResolveWithoutDirectForeignFields(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $fieldArray = ['resolved_date' => time()];

        $this->createHook()->processDatamap_afterDatabaseOperations('update', Configuration::TABLE_COMMENT, 1, $fieldArray, $dataHandler);

        $record = $this->get(RecordRepository::class)->findByUid('pages', 10);
        self::assertIsArray($record);
        self::assertSame(2, (int) $record['tx_ximatypo3contentplanner_comments']);
    }

    #[Test]
    public function afterDatabaseOperationsRefreshesPageTreeWhenPageStatusChanges(): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $fieldArray = [Configuration::FIELD_STATUS => 2];

        // BackendUtility::setUpdateSignal() requires a real backend user session, present via
        // setUp()'s loginBackendUser(). Exercising this line is the point of the test.
        $this->createHook()->processDatamap_afterDatabaseOperations('update', 'pages', 1, $fieldArray, $dataHandler);

        self::expectNotToPerformAssertions();
    }

    private function deleteStatus(int $uid): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $unused = null;
        $this->createHook()->processCmdmap_preProcess(
            'delete',
            self::STATUS_TABLE,
            $uid,
            $unused,
            $dataHandler,
            null,
        );
    }

    /**
     * The other cases mock the cache away; this one needs the real one, because the flush
     * is the thing under test.
     */
    private function deleteStatusWithRealCache(int $uid): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $unused = null;
        $hook = new DataHandlerHook(
            $this->get(CacheManager::class)->getCache(Configuration::CACHE_IDENTIFIER.'_cache'),
            $this->get(StatusChangeManager::class),
            $this->get(RecordRepository::class),
            $this->get(CommentRepository::class),
            $this->get(EventDispatcherInterface::class),
            $this->get(StatusDefaultManager::class),
        );

        $hook->processCmdmap_preProcess('delete', self::STATUS_TABLE, $uid, $unused, $dataHandler, null);
    }

    private function cachedStatusOfPage(RecordRepository $recordRepository, int $uid): ?int
    {
        foreach ($recordRepository->findByPid('pages', 0) as $row) {
            if ((int) $row['uid'] === $uid) {
                return null === $row[Configuration::FIELD_STATUS] ? null : (int) $row[Configuration::FIELD_STATUS];
            }
        }

        return null;
    }

    private function statusOfPage(int $uid): ?int
    {
        $value = $this->getConnectionPool()->getConnectionForTable('pages')
            ->select([Configuration::FIELD_STATUS], 'pages', ['uid' => $uid])
            ->fetchOne();

        return null === $value || 0 === (int) $value ? null : (int) $value;
    }

    private function createHook(): DataHandlerHook
    {
        return new DataHandlerHook(
            $this->createMock(FrontendInterface::class),
            $this->get(StatusChangeManager::class),
            $this->get(RecordRepository::class),
            $this->get(CommentRepository::class),
            $this->get(EventDispatcherInterface::class),
            $this->get(StatusDefaultManager::class),
        );
    }

    private function createHookWithDispatcher(EventDispatcherInterface $dispatcher): DataHandlerHook
    {
        return new DataHandlerHook(
            $this->createMock(FrontendInterface::class),
            $this->get(StatusChangeManager::class),
            $this->get(RecordRepository::class),
            $this->get(CommentRepository::class),
            $dispatcher,
            $this->get(StatusDefaultManager::class),
        );
    }
}
