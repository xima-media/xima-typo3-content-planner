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
use TYPO3\CMS\Core\Cache\CacheManager;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Manager\CommentFirstFlowManager;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * CommentFirstFlowManagerTest.
 *
 * CP-27 (#326): covers the decision between "nothing" (record already has a status, or the
 * user cannot change status at all), the one-click flow (an "is_default" status exists and is
 * allowed) and the inline picker fallback (no usable default).
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CommentFirstFlowManagerTest extends AbstractFunctionalTestCase
{
    private CommentFirstFlowManager $subject;

    protected function setUp(): void
    {
        parent::setUp();
        // The status list is cached under a fixed identifier ('...--status--all'), regardless
        // of which fixture populated the table - flush it so a test importing status.csv is
        // never served the status_default.csv content another test cached first.
        $this->get(CacheManager::class)->getCache(Configuration::CACHE_IDENTIFIER.'_cache')->flush();
        $this->subject = $this->get(CommentFirstFlowManager::class);
    }

    #[Test]
    public function buildContextIsInactiveWhenRecordAlreadyHasAStatus(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/status.csv');

        $context = $this->subject->buildContext([Configuration::FIELD_STATUS => 2]);

        self::assertFalse($context['active']);
    }

    #[Test]
    public function buildContextIsInactiveWhenUserCannotChangeStatusAtAll(): void
    {
        // Editor (uid 2) is a non-admin without any content planner permission group.
        $this->loginBackendUser(2);
        $this->importCSVDataSet(__DIR__.'/Fixtures/status.csv');

        $context = $this->subject->buildContext([Configuration::FIELD_STATUS => null]);

        self::assertFalse($context['active']);
    }

    #[Test]
    public function buildContextUsesOneClickModeWhenADefaultStatusIsConfigured(): void
    {
        $this->loginBackendUser(1);
        $this->importSharedDataSet('status_default.csv');

        $context = $this->subject->buildContext([Configuration::FIELD_STATUS => 0]);

        self::assertTrue($context['active']);
        self::assertSame('oneClick', $context['mode']);
        self::assertSame(1, $context['defaultStatus']['uid']);
        self::assertSame('Draft', $context['defaultStatus']['title']);
    }

    #[Test]
    public function buildContextFallsBackToPickerModeWithoutADefaultStatus(): void
    {
        $this->loginBackendUser(1);
        $this->importCSVDataSet(__DIR__.'/Fixtures/status.csv');

        $context = $this->subject->buildContext([Configuration::FIELD_STATUS => 0]);

        self::assertTrue($context['active']);
        self::assertSame('picker', $context['mode']);
        self::assertCount(2, $context['statuses']);
    }

    #[Test]
    public function resolveStatusUidForCommentFirstReturnsNullWhenRecordAlreadyHasAStatus(): void
    {
        self::assertNull($this->subject->resolveStatusUidForCommentFirst([Configuration::FIELD_STATUS => 2], 1));
    }

    #[Test]
    public function resolveStatusUidForCommentFirstReturnsNullWhenNoStatusWasRequested(): void
    {
        self::assertNull($this->subject->resolveStatusUidForCommentFirst([Configuration::FIELD_STATUS => 0], null));
        self::assertNull($this->subject->resolveStatusUidForCommentFirst([Configuration::FIELD_STATUS => 0], 0));
    }

    #[Test]
    public function resolveStatusUidForCommentFirstReturnsTheRequestedUidOnAStatusLessRecord(): void
    {
        self::assertSame(3, $this->subject->resolveStatusUidForCommentFirst([Configuration::FIELD_STATUS => 0], 3));
    }
}
