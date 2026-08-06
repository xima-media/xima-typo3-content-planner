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
