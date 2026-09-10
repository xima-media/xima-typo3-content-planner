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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Hooks;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Hooks\DataHandlerHook;
use Xima\XimaTypo3ContentPlanner\Manager\{StatusChangeManager, StatusDefaultManager};

/**
 * DataHandlerHookTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class DataHandlerHookTest extends TestCase
{
    public function testRefreshesPageTreeWhenPageStatusIsChanged(): void
    {
        self::assertTrue(
            $this->createHook()->shouldRefreshPageTree('pages', [Configuration::FIELD_STATUS => 2]),
        );
    }

    public function testRefreshesPageTreeWhenPageStatusIsReset(): void
    {
        self::assertTrue(
            $this->createHook()->shouldRefreshPageTree('pages', [Configuration::FIELD_STATUS => null]),
        );
    }

    public function testDoesNotRefreshWhenStatusFieldIsAbsent(): void
    {
        self::assertFalse(
            $this->createHook()->shouldRefreshPageTree('pages', ['title' => 'New title']),
        );
    }

    public function testDoesNotRefreshForNonPageTable(): void
    {
        self::assertFalse(
            $this->createHook()->shouldRefreshPageTree('tx_news_domain_model_news', [Configuration::FIELD_STATUS => 2]),
        );
    }

    public function testClearCachePostProcAddsPageIdTagWhenTableAndUidPagePresent(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->expects(self::once())
            ->method('flushByTags')
            ->with(self::identicalTo(['existing', 'pages__pageId__5']));

        $this->createHook($cache)->clearCachePostProc([
            'tags' => ['existing'],
            'table' => 'pages',
            'uid_page' => 5,
        ]);
    }

    public function testClearCachePostProcForwardsOnlyCoreTagsWhenPageInfoAbsent(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->expects(self::once())
            ->method('flushByTags')
            ->with(self::identicalTo(['existing']));

        $this->createHook($cache)->clearCachePostProc(['tags' => ['existing']]);
    }

    private function createHook(?FrontendInterface $cache = null): DataHandlerHook
    {
        return new DataHandlerHook(
            $cache ?? $this->createMock(FrontendInterface::class),
            $this->createMock(StatusChangeManager::class),
            $this->createMock(RecordRepository::class),
            $this->createMock(CommentRepository::class),
            $this->createMock(EventDispatcherInterface::class),
            new StatusDefaultManager($this->createMock(ConnectionPool::class), $this->createMock(FrontendInterface::class)),
        );
    }
}
