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
use Xima\XimaTypo3ContentPlanner\Service\{RecordPresetService, WatcherService};
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
    public function indexActionRendersThePresetsAsToggleChipsWithLabelCountAndPressedState(): void
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

        // "assignee=1" is applied and matches backend user 1, so only that chip is pressed.
        $response = $this->createController($recordRepository)->indexAction($this->createRequest(['assignee' => '1']));
        $body = (string) $response->getBody();

        self::assertSame(1, substr_count($body, 'aria-pressed="true"'));
        self::assertSame(2, substr_count($body, 'aria-pressed="false"'));
        // The label never changes with the state and the count sits next to it.
        self::assertMatchesRegularExpression('#aria-pressed="true"[^>]*>\s*(?:<[^>]+>\s*)*Assigned to me\s*(?:<[^>]+>\s*)*2\b#', $body);
        self::assertMatchesRegularExpression('#aria-pressed="false"[^>]*>\s*(?:<[^>]+>\s*)*Open to-dos\s*(?:<[^>]+>\s*)*1\b#', $body);
        self::assertStringContainsString('Open comments', $body);
        // A pressed chip switches itself off, the others switch themselves on.
        self::assertMatchesRegularExpression('#<a href="((?:(?!assignee=)[^"])*)"[^>]*aria-pressed="true"#', $body);
        self::assertMatchesRegularExpression('#<a href="[^"]*todo=1[^"]*"[^>]*aria-pressed="false"#', $body);
        self::assertMatchesRegularExpression('#<a href="[^"]*openComments=1[^"]*"[^>]*aria-pressed="false"#', $body);
    }

    #[Test]
    public function indexActionRendersTheFiltersInlineWithoutAMoreFiltersToggle(): void
    {
        $this->loginBackendUser(1);
        $this->importSharedDataSet('status.csv');

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findAllByFilter')->willReturn(new PaginatedResult([], false));

        $response = $this->createController($recordRepository)->indexAction($this->createRequest(['status' => '2']));
        $body = (string) $response->getBody();

        self::assertStringNotContainsString('More filters', $body);
        self::assertStringNotContainsString('recordFilterAdvanced', $body);
        self::assertStringContainsString('All statuses', $body);
        self::assertStringContainsString('All assignees', $body);
        // The select keeps its value, status.csv uid 2 is "In Progress".
        self::assertMatchesRegularExpression('#<option value="2"\s+selected="selected">In Progress#', $body);
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
    public function indexActionOpensTheRecordModalFromTheStatusAndAssigneeCells(): void
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

        // Both records have an assignee cell that opens the modal, only the assigned one names it.
        self::assertSame(2, substr_count($body, 'data-content-planner-assignees'));
        self::assertSame(1, substr_count($body, 'data-current-assignee="1"'));
        // Neither record has comments, so the only comments trigger is the status of the record that has one.
        self::assertSame(1, substr_count($body, 'data-content-planner-comments'));
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

        self::assertStringContainsString('Record Type', $body);
        self::assertStringContainsString('Page', $body);
        // The Type cell links into the same list filtered by that table (CP-33 follow-up),
        // like the Status/Assignee cells.
        self::assertStringContainsString('type=pages', $body);
    }

    #[Test]
    public function indexActionHasNoRemovableFilterChipRow(): void
    {
        $this->loginBackendUser(1);
        $this->importSharedDataSet('status.csv');

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findAllByFilter')->willReturn(new PaginatedResult([], false));

        // Every dimension is visible where it is set: the search field, the selects, the pressed chips.
        $response = $this->createController($recordRepository)->indexAction(
            $this->createRequest(['search' => 'foo', 'status' => '2', 'todo' => '1']),
        );
        $body = (string) $response->getBody();

        self::assertStringNotContainsString('content-planner-module__chip"', $body);
        self::assertStringNotContainsString('Search: foo', $body);
    }

    #[Test]
    public function indexActionRendersTheRecordRowAsBadgeTagPathAndActivity(): void
    {
        $this->loginBackendUser(1);
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages_tree.csv');
        $this->enableExtensionFeature('registerAdditionalRecordTables', ['tt_content']);
        $this->enableExtensionFeature('currentAssigneeHighlight', '1');

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findAllByFilter')->willReturnCallback(
            static function (?string $search, ?int $status, ?int $assignee, ?string $type, ?bool $todo, int $maxResults, bool $openComments = false, ?array $watchedRecords = null, int $offset = 0): PaginatedResult {
                if (RecordRepository::DEFAULT_PAGE_SIZE === $maxResults) {
                    return new PaginatedResult([
                        [
                            'uid' => 5,
                            'pid' => 22,
                            'tablename' => 'tt_content',
                            'title' => 'Teaser',
                            'tstamp' => 1700000000,
                            'tx_ximatypo3contentplanner_status' => 2,
                            'tx_ximatypo3contentplanner_assignee' => 1,
                            'tx_ximatypo3contentplanner_comments' => 3,
                        ],
                        [
                            'uid' => 6,
                            'pid' => 22,
                            'tablename' => 'tt_content',
                            'title' => 'Footer',
                            'tstamp' => 1700000000,
                            'tx_ximatypo3contentplanner_status' => 3,
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

        // Status as the shared badge, in its own colour, and with the name as text.
        self::assertStringContainsString('content-planner-status-badge content-planner-status content-planner-status--yellow', $body);
        self::assertStringContainsString('content-planner-status--green', $body);
        // Where the record lives sits on a second line instead of a Site column.
        self::assertStringContainsString('Parent › Child › Leaf', $body);
        // The current user's record carries a tag, not a tinted row.
        self::assertStringContainsString('Assigned to you', $body);
        self::assertStringNotContainsString('table-info', $body);
        // Counts carry their name as text, and an unassigned record says so.
        self::assertStringContainsString('3 comments', $body);
        self::assertStringContainsString('Unassigned', $body);
    }

    #[Test]
    public function indexActionMarksTheSortedColumnForAssistiveTechnology(): void
    {
        $this->loginBackendUser(1);

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findAllByFilter')->willReturn(new PaginatedResult([[
            'uid' => 1,
            'pid' => 0,
            'tablename' => 'pages',
            'title' => 'Home',
            'tstamp' => 1700000000,
            'tx_ximatypo3contentplanner_status' => 0,
            'tx_ximatypo3contentplanner_assignee' => 0,
            'tx_ximatypo3contentplanner_comments' => 0,
        ]], false));

        $body = (string) $this->createController($recordRepository)->indexAction($this->createRequest([]))->getBody();

        // Default sort: changed, descending. The other sortable column carries none.
        self::assertSame(1, substr_count($body, 'aria-sort='));
        self::assertStringContainsString('aria-sort="descending"', $body);

        $body = (string) $this->createController($recordRepository)->indexAction($this->createRequest(['sort' => 'title', 'dir' => 'asc']))->getBody();

        self::assertStringContainsString('aria-sort="ascending"', $body);
        self::assertStringNotContainsString('aria-sort="descending"', $body);
    }

    #[Test]
    public function indexActionKeepsThePaginationVisibleAndDisablesWhatIsNotAvailable(): void
    {
        $this->loginBackendUser(1);

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findAllByFilter')->willReturn(new PaginatedResult([[
            'uid' => 1,
            'pid' => 0,
            'tablename' => 'pages',
            'title' => 'Home',
            'tstamp' => 1700000000,
            'tx_ximatypo3contentplanner_status' => 0,
            'tx_ximatypo3contentplanner_assignee' => 0,
            'tx_ximatypo3contentplanner_comments' => 0,
        ]], false));

        $body = (string) $this->createController($recordRepository)->indexAction($this->createRequest([]))->getBody();

        self::assertSame(2, substr_count($body, 'aria-disabled="true"'));
        self::assertStringContainsString('Previous', $body);
        self::assertStringContainsString('Next', $body);
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

    #[Test]
    public function indexActionDefaultsToSortingByChangedDescending(): void
    {
        $this->loginBackendUser(1);

        $capturedSortField = null;
        $capturedSortDirection = null;

        $pageRow = [
            'uid' => 1,
            'pid' => 0,
            'tablename' => 'pages',
            'title' => 'Home',
            'tstamp' => 1700000000,
            'tx_ximatypo3contentplanner_status' => 0,
            'tx_ximatypo3contentplanner_assignee' => 0,
            'tx_ximatypo3contentplanner_comments' => 0,
        ];

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findAllByFilter')->willReturnCallback(
            static function (?string $search, ?int $status, ?int $assignee, ?string $type, ?bool $todo, int $maxResults, bool $openComments = false, ?array $watchedRecords = null, int $offset = 0, string $sortField = 'changed', string $sortDirection = 'desc') use (&$capturedSortField, &$capturedSortDirection, $pageRow): PaginatedResult {
                if (RecordRepository::DEFAULT_PAGE_SIZE === $maxResults) {
                    $capturedSortField = $sortField;
                    $capturedSortDirection = $sortDirection;

                    return new PaginatedResult([$pageRow], false);
                }

                return new PaginatedResult([], false);
            },
        );

        $response = $this->createController($recordRepository)->indexAction($this->createRequest([]));
        $body = (string) $response->getBody();

        self::assertSame('changed', $capturedSortField);
        self::assertSame('desc', $capturedSortDirection);
        // Title is not the active sort column, so clicking it should sort ascending by default.
        self::assertStringContainsString('sort=title', $body);
        self::assertStringContainsString('dir=asc', $body);
    }

    #[Test]
    public function indexActionThreadsSortAndDirectionQueryParamsToTheRepositoryAndPreservesThemAcrossPagination(): void
    {
        $this->loginBackendUser(1);

        $capturedSortField = null;
        $capturedSortDirection = null;

        $pageRow = [
            'uid' => 1,
            'pid' => 0,
            'tablename' => 'pages',
            'title' => 'Home',
            'tstamp' => 1700000000,
            'tx_ximatypo3contentplanner_status' => 0,
            'tx_ximatypo3contentplanner_assignee' => 0,
            'tx_ximatypo3contentplanner_comments' => 0,
        ];

        $recordRepository = $this->createMock(RecordRepository::class);
        $recordRepository->method('findAllByFilter')->willReturnCallback(
            static function (?string $search, ?int $status, ?int $assignee, ?string $type, ?bool $todo, int $maxResults, bool $openComments = false, ?array $watchedRecords = null, int $offset = 0, string $sortField = 'changed', string $sortDirection = 'desc') use (&$capturedSortField, &$capturedSortDirection, $pageRow): PaginatedResult {
                if (RecordRepository::DEFAULT_PAGE_SIZE === $maxResults) {
                    $capturedSortField = $sortField;
                    $capturedSortDirection = $sortDirection;

                    return new PaginatedResult([$pageRow], true);
                }

                return new PaginatedResult([], false);
            },
        );

        $response = $this->createController($recordRepository)->indexAction(
            $this->createRequest(['sort' => 'title', 'dir' => 'asc']),
        );
        $body = (string) $response->getBody();

        self::assertSame('title', $capturedSortField);
        self::assertSame('asc', $capturedSortDirection);
        // Title is the active column ascending - clicking it again must flip to descending.
        self::assertStringContainsString('sort=title', $body);
        self::assertStringContainsString('dir=desc', $body);
        // The next-page link must carry the current sort along, or paginating would silently
        // reset back to the default "changed desc" order.
        self::assertMatchesRegularExpression('/href="[^"]*page=2[^"]*sort=title[^"]*"|href="[^"]*sort=title[^"]*page=2[^"]*"/', $body);
    }

    private function createController(?RecordRepository $recordRepository = null, ?WatcherService $watcherService = null): RecordModuleController
    {
        $recordRepository ??= $this->get(RecordRepository::class);

        return new RecordModuleController(
            $this->get(ModuleTemplateFactory::class),
            $this->get(UriBuilder::class),
            $recordRepository,
            $this->get(StatusRepository::class),
            $this->get(BackendUserRepository::class),
            $watcherService ?? $this->get(WatcherService::class),
            new RecordPresetService($recordRepository),
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
