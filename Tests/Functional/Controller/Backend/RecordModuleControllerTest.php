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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Controller\Backend;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\{Route, UriBuilder};
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use Xima\XimaTypo3ContentPlanner\Controller\Backend\RecordModuleController;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\PaginatedResult;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Service\WatcherService;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * RecordModuleControllerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class RecordModuleControllerTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function indexActionComputesOffsetFromThePageQueryParameter(): void
    {
        $this->loginBackendUser(1);

        // CP-32 (#404): page 3 at the default page size of 20 must skip the first 40 visible
        // records - this is the whole point of the offset parameter #404 adds. The preset
        // tiles (CP-33 follow-up) issue three more findAllByFilter() calls of their own, each
        // with maxResults = PRESET_COUNT_LIMIT rather than DEFAULT_PAGE_SIZE, so only the call
        // matching the page-size argument is the one this test cares about.
        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findAllByFilter')
            ->willReturnCallback(static function (?string $search, ?int $status, ?int $assignee, ?string $type, ?bool $todo, int $maxResults, bool $openComments = false, ?array $watchedRecords = null, int $offset = 0): PaginatedResult {
                if (RecordRepository::DEFAULT_PAGE_SIZE === $maxResults) {
                    self::assertSame(40, $offset);
                }

                return new PaginatedResult([], false);
            });

        $response = $this->createController($recordRepository)->indexAction($this->createRequest(['page' => '3']));

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function indexActionResolvesWatchedRecordsForTheCurrentUserWhenWatchedFilterIsSet(): void
    {
        $this->loginBackendUser(1);

        $watcherService = $this->createMock(WatcherService::class);
        $watcherService->expects(self::once())
            ->method('getWatchedRecords')
            ->with(1)
            ->willReturn(['pages' => [5]]);

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findAllByFilter')
            ->willReturnCallback(static function (?string $search, ?int $status, ?int $assignee, ?string $type, ?bool $todo, int $maxResults, bool $openComments = false, ?array $watchedRecords = null, int $offset = 0): PaginatedResult {
                if (RecordRepository::DEFAULT_PAGE_SIZE === $maxResults) {
                    self::assertSame(['pages' => [5]], $watchedRecords);
                }

                return new PaginatedResult([], false);
            });

        $response = $this->createController($recordRepository, $watcherService)->indexAction(
            $this->createRequest(['watched' => '1']),
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function indexActionRendersPresetTilesWithCountsLinksAndActiveState(): void
    {
        $this->loginBackendUser(1);

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findAllByFilter')->willReturnCallback(
            static function (?string $search, ?int $status, ?int $assignee, ?string $type, ?bool $todo, int $maxResults, bool $openComments = false, ?array $watchedRecords = null, int $offset = 0): PaginatedResult {
                if (RecordRepository::DEFAULT_PAGE_SIZE === $maxResults) {
                    return new PaginatedResult([], false);
                }
                if (1 === $assignee) {
                    return new PaginatedResult([['uid' => 1], ['uid' => 2]], false);
                }
                if (true === $todo) {
                    return new PaginatedResult([['uid' => 3]], false);
                }

                return new PaginatedResult([], false);
            },
        );

        // "assignee=1" filter is currently applied, matching backend user 1's own uid - the
        // assignee preset tile must render as active for it, the todo/openComments ones not.
        $response = $this->createController($recordRepository)->indexAction($this->createRequest(['assignee' => '1']));
        $body = (string) $response->getBody();

        self::assertStringContainsString('content-planner-module__preset', $body);
        self::assertStringContainsString('assignee=1', $body);
        self::assertStringContainsString('todo=1', $body);
        self::assertStringContainsString('openComments=1', $body);
        self::assertStringContainsString('content-planner-module__preset--active', $body);
        self::assertMatchesRegularExpression('#preset-figure" aria-hidden="true">2<#', $body);
        self::assertMatchesRegularExpression('#preset-figure" aria-hidden="true">1<#', $body);
    }

    #[Test]
    public function indexActionComputesActiveAdvancedFilterCountExcludingSearchTodoAndOpenComments(): void
    {
        $this->loginBackendUser(1);

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findAllByFilter')->willReturn(new PaginatedResult([], false));

        // "search" lives in the primary row, "todo"/"openComments" moved to preset tiles -
        // only "status" should count toward the collapsible advanced panel's badge.
        $response = $this->createController($recordRepository)->indexAction(
            $this->createRequest(['search' => 'foo', 'status' => '1', 'todo' => '1', 'openComments' => '1']),
        );
        $body = (string) $response->getBody();

        self::assertStringContainsString('badge badge-primary">1<', $body);
    }

    #[Test]
    public function indexActionRendersPaginationRange(): void
    {
        $this->loginBackendUser(1);
        $this->importSharedDataSet('status.csv');

        // A row shape StatusItem::create() (and its icon/link rendering) can actually handle -
        // see StatusItemTest::pageRow() for the same minimal set.
        $pageRow = static fn (int $uid): array => [
            'uid' => $uid,
            'pid' => 0,
            'tablename' => 'pages',
            'title' => 'Home '.$uid,
            'tstamp' => 1700000000,
            'tx_ximatypo3contentplanner_status' => 2,
            'tx_ximatypo3contentplanner_assignee' => 0,
            'tx_ximatypo3contentplanner_comments' => 0,
        ];

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findAllByFilter')->willReturnCallback(
            static function (?string $search, ?int $status, ?int $assignee, ?string $type, ?bool $todo, int $maxResults, bool $openComments = false, ?array $watchedRecords = null, int $offset = 0) use ($pageRow): PaginatedResult {
                if (RecordRepository::DEFAULT_PAGE_SIZE === $maxResults) {
                    return new PaginatedResult([$pageRow(1), $pageRow(2), $pageRow(3)], false);
                }

                return new PaginatedResult([], false);
            },
        );

        $response = $this->createController($recordRepository)->indexAction($this->createRequest([]));
        $body = (string) $response->getBody();

        self::assertStringContainsString('Showing 1–3', $body);
        // Regression: the status/record icons must be rendered directly (they are already
        // fully-rendered markup from IconUtility, not bare identifiers) - wrapping them in a
        // second <core:icon identifier="..."> made TYPO3 try to resolve that markup string as
        // an icon identifier and silently fall back to "not found".
        self::assertStringNotContainsString('icon-default-not-found', $body);
        // The Title/Status split (CP-33 follow-up): the status now has its own column with the
        // status title as real text, not just an icon.
        self::assertStringContainsString('In Progress', $body);
    }

    #[Test]
    public function indexActionLinksStatusAndAssigneeCellsToTheMatchingFilterButOmitsAnUnassignedLink(): void
    {
        $this->loginBackendUser(1);
        $this->importSharedDataSet('status.csv');

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findAllByFilter')->willReturnCallback(
            static function (?string $search, ?int $status, ?int $assignee, ?string $type, ?bool $todo, int $maxResults, bool $openComments = false, ?array $watchedRecords = null, int $offset = 0): PaginatedResult {
                if (RecordRepository::DEFAULT_PAGE_SIZE === $maxResults) {
                    return new PaginatedResult([
                        [
                            'uid' => 1,
                            'pid' => 0,
                            'tablename' => 'pages',
                            'title' => 'Assigned',
                            'tstamp' => 1700000000,
                            'tx_ximatypo3contentplanner_status' => 2,
                            'tx_ximatypo3contentplanner_assignee' => 1,
                            'tx_ximatypo3contentplanner_comments' => 0,
                        ],
                        [
                            'uid' => 2,
                            'pid' => 0,
                            'tablename' => 'pages',
                            'title' => 'Unassigned',
                            'tstamp' => 1700000000,
                            'tx_ximatypo3contentplanner_status' => 0,
                            'tx_ximatypo3contentplanner_assignee' => 0,
                            'tx_ximatypo3contentplanner_comments' => 0,
                        ],
                    ], false);
                }

                return new PaginatedResult([], false);
            },
        );

        $response = $this->createController($recordRepository)->indexAction($this->createRequest([]));
        $body = (string) $response->getBody();

        self::assertStringContainsString('content-planner-module__cell-link', $body);
        self::assertStringContainsString('status=2', $body);
        self::assertStringContainsString('assignee=1', $body);
        // record uid 2 has neither a status nor an assignee - the Title/Site/etc. columns
        // must not silently link to "status=0" or "assignee=0", which would just be an
        // always-empty filter.
        self::assertStringNotContainsString('status=0', $body);
        self::assertStringNotContainsString('assignee=0', $body);
    }

    #[Test]
    public function indexActionRendersATypeColumnWhenMultipleRecordTablesAreTracked(): void
    {
        $this->loginBackendUser(1);
        $this->enableExtensionFeature('registerAdditionalRecordTables', ['tt_content']);

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findAllByFilter')->willReturnCallback(
            static function (?string $search, ?int $status, ?int $assignee, ?string $type, ?bool $todo, int $maxResults, bool $openComments = false, ?array $watchedRecords = null, int $offset = 0): PaginatedResult {
                if (RecordRepository::DEFAULT_PAGE_SIZE === $maxResults) {
                    return new PaginatedResult([[
                        'uid' => 1,
                        'pid' => 0,
                        'tablename' => 'pages',
                        'title' => 'Home',
                        'tstamp' => 1700000000,
                        'tx_ximatypo3contentplanner_status' => 0,
                        'tx_ximatypo3contentplanner_assignee' => 0,
                        'tx_ximatypo3contentplanner_comments' => 0,
                    ]], false);
                }

                return new PaginatedResult([], false);
            },
        );

        $response = $this->createController($recordRepository)->indexAction($this->createRequest([]));
        $body = (string) $response->getBody();

        self::assertStringContainsString('content-planner-module__table--with-type', $body);
        self::assertStringContainsString('Page', $body);
        // The Type cell links into the same list filtered by that table (CP-33 follow-up),
        // like the Status/Assignee cells.
        self::assertStringContainsString('type=pages', $body);
    }

    #[Test]
    public function indexActionRendersActiveFilterChipsWithRemoveLinksForEachDimension(): void
    {
        $this->loginBackendUser(1);
        $this->importSharedDataSet('status.csv');

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findAllByFilter')->willReturn(new PaginatedResult([], false));

        $response = $this->createController($recordRepository)->indexAction(
            $this->createRequest(['search' => 'foo', 'status' => '2', 'todo' => '1']),
        );
        $body = (string) $response->getBody();

        self::assertStringContainsString('content-planner-module__chip', $body);
        self::assertStringContainsString('Search: foo', $body);
        // status.csv uid 2 is "In Progress"
        self::assertStringContainsString('Status: In Progress', $body);
        self::assertStringContainsString('Open TODOs', $body);
        // each chip must remove only its own dimension, keeping the others
        self::assertStringContainsString('search=foo&amp;todo=1', $body);
        self::assertStringContainsString('search=foo&amp;status=2', $body);
    }

    #[Test]
    public function indexActionRendersNoFilterChipsWhenNothingIsFiltered(): void
    {
        $this->loginBackendUser(1);

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findAllByFilter')->willReturn(new PaginatedResult([], false));

        $response = $this->createController($recordRepository)->indexAction($this->createRequest([]));
        $body = (string) $response->getBody();

        self::assertStringNotContainsString('content-planner-module__chip"', $body);
    }

    #[Test]
    public function indexActionReturns403WhenContentStatusVisibilityIsDenied(): void
    {
        // Editor (uid 2) is a non-admin without any content planner permission, the same
        // fixture user RecordControllerTest uses for the equivalent AJAX-facing denial.
        $this->loginBackendUser(2);

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->expects(self::never())->method('findAllByFilter');

        $response = $this->createController($recordRepository)->indexAction($this->createRequest([]));

        self::assertSame(403, $response->getStatusCode());
    }

    private function createController(?RecordRepository $recordRepository = null, ?WatcherService $watcherService = null): RecordModuleController
    {
        return new RecordModuleController(
            $this->get(ModuleTemplateFactory::class),
            $this->get(UriBuilder::class),
            $recordRepository ?? $this->get(RecordRepository::class),
            $this->get(StatusRepository::class),
            $this->get(BackendUserRepository::class),
            $watcherService ?? $this->get(WatcherService::class),
            $this->get(PageRenderer::class),
        );
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function createRequest(array $queryParams): ServerRequestInterface
    {
        $moduleProvider = $this->get(ModuleProvider::class);
        $module = $moduleProvider->getModule('content_planner_records');
        self::assertNotNull($module, 'content_planner_records module must be registered');

        // BackendViewFactory reads the singular 'route' attribute (not the 'routing' RouteResult
        // that setUpBackendRequest() sets) to resolve which extension's own
        // Resources/Private/Templates to search; 'packageName' is normally filled in by TYPO3's
        // own module-to-route compilation, which calling the controller directly bypasses, so it
        // is set explicitly here.
        $route = new Route('/module/content-planner/records', [
            '_identifier' => 'content_planner_records',
            'packageName' => 'xima/xima-typo3-content-planner',
        ]);

        // setUpBackendRequest() also assigns $GLOBALS['TYPO3_REQUEST'], which
        // ModifyButtonBarEventListener (fired while the doc header renders) reads directly.
        $request = $this->setUpBackendRequest('content_planner_records', $queryParams)
            ->withAttribute('module', $module)
            ->withAttribute('route', $route);
        $GLOBALS['TYPO3_REQUEST'] = $request;

        return $request;
    }
}
