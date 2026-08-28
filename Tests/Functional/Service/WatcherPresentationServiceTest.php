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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\WatchSource;
use Xima\XimaTypo3ContentPlanner\Service\{WatcherPresentationService, WatcherService};
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * WatcherPresentationServiceTest.
 *
 * Covers the read model behind the watch/unwatch toggle UI (issue #303): the four visual states
 * (not watching / watching-auto / watching-manual / muted) and the permission-filtered watcher
 * name list, mirroring the visibility pool already used by the assignee picker and the @-mention
 * suggestion feed.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class WatcherPresentationServiceTest extends AbstractFunctionalTestCase
{
    private WatcherPresentationService $subject;
    private WatcherService $watcherService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('be_users.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->subject = $this->get(WatcherPresentationService::class);
        $this->watcherService = $this->get(WatcherService::class);
    }

    #[Test]
    public function buildReturnsNotWatchingStateForAnUnwatchedRecord(): void
    {
        $result = $this->subject->build('pages', 1, 1);

        self::assertTrue($result['watchable']);
        self::assertNull($result['mode']);
        self::assertFalse($result['watching']);
        self::assertSame(0, $result['count']);
        self::assertSame([], $result['watcherNames']);
    }

    #[Test]
    public function buildReturnsAutoModeAndWatchingTrueForAnAutoWatchedRecord(): void
    {
        $this->watcherService->watch('pages', 1, 1, WatchSource::Assignment);

        $result = $this->subject->build('pages', 1, 1);

        self::assertSame('auto', $result['mode']);
        self::assertTrue($result['watching']);
    }

    #[Test]
    public function buildReturnsManualWatchModeAndWatchingTrueForAManuallyWatchedRecord(): void
    {
        $this->watcherService->watch('pages', 1, 1, WatchSource::Manual);

        $result = $this->subject->build('pages', 1, 1);

        self::assertSame('manual_watch', $result['mode']);
        self::assertTrue($result['watching']);
    }

    #[Test]
    public function buildReturnsManualUnwatchModeAndWatchingFalseForAMutedRecord(): void
    {
        $this->watcherService->unwatch('pages', 1, 1);

        $result = $this->subject->build('pages', 1, 1);

        self::assertSame('manual_unwatch', $result['mode']);
        self::assertFalse($result['watching']);
    }

    #[Test]
    public function buildReturnsAnUnwatchableStateForAnExplicitlyExcludedTable(): void
    {
        $result = $this->subject->build(Configuration::TABLE_FOLDER, 1, 1);

        self::assertFalse($result['watchable']);
        self::assertNull($result['mode']);
        self::assertFalse($result['watching']);
        self::assertSame(0, $result['count']);
        self::assertSame([], $result['watcherNames']);
    }

    /**
     * Regression test: `sys_file_metadata` becomes a registered record table (and therefore a
     * candidate for the record-edit status banner) once `enableFilelistSupport` is on, but
     * {@see WatcherService} explicitly excludes it from watching (see its class docblock).
     * `watchable` must be `false` here too, so the Fluid partial's
     * `<f:if condition="{watch.watchable}">` - not a truthiness check on the whole array - is
     * what actually hides the toggle for this table.
     */
    #[Test]
    public function buildReturnsAnUnwatchableStateForSysFileMetadataEvenWithFilelistSupportEnabled(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['enableFilelistSupport'] = true;
        try {
            // With the feature on, sys_file_metadata IS a registered record table (reachable via
            // the record-edit status banner) - it must still be reported as unwatchable.
            $result = $this->subject->build('sys_file_metadata', 1, 1);

            self::assertFalse($result['watchable']);
        } finally {
            unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['enableFilelistSupport']);
        }
    }

    #[Test]
    public function buildCountsOnlyActiveWatchersExcludingDisabledAndDeletedBackendUsers(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/be_groups_watcher.csv');
        $this->watcherService->watch('pages', 1, 10, WatchSource::Manual); // member: active
        // uid 11 ("nogroup") has no content-planner permission but is still an active backend
        // user - counted (just not named, see the next test).
        $this->watcherService->watch('pages', 1, 11, WatchSource::Manual);
        $this->watcherService->watch('pages', 1, 12, WatchSource::Manual); // disabled: excluded
        $this->watcherService->watch('pages', 1, 13, WatchSource::Manual); // deleted: excluded

        $result = $this->subject->build('pages', 1, 1);

        self::assertSame(2, $result['count'], 'a disabled or deleted backend user must never inflate the watcher count');
    }

    #[Test]
    public function buildFiltersWatcherNamesToThePermittedPoolOnly(): void
    {
        $this->importCSVDataSet(__DIR__.'/Fixtures/be_groups_watcher.csv');
        $this->watcherService->watch('pages', 1, 10, WatchSource::Manual); // member: permitted
        $this->watcherService->watch('pages', 1, 11, WatchSource::Manual); // nogroup: not permitted

        $result = $this->subject->build('pages', 1, 1);

        self::assertTrue($result['watchable']);
        self::assertSame(['Member User (member)'], $result['watcherNames']);
    }
}
