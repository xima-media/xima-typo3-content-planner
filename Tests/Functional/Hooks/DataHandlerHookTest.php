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
use Xima\XimaTypo3ContentPlanner\Hooks\DataHandlerHook;
use Xima\XimaTypo3ContentPlanner\Manager\StatusChangeManager;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

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
        );
    }
}
