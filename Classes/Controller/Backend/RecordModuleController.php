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

namespace Xima\XimaTypo3ContentPlanner\Controller\Backend;

use Doctrine\DBAL\Exception;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\StatusItem;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Service\WatcherService;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function array_filter;
use function array_key_exists;
use function array_map;
use function count;
use function in_array;

/**
 * RecordModuleController.
 *
 * Backend module home for the filterable record list (CP-32, #404). Unlike the AJAX-driven
 * `contentPlanner-status` dashboard widget (`RecordController::filterAction()`), this renders
 * server-side with the full filter and pagination state in the URL's query string - a plain GET
 * request produces a shareable, bookmarkable link, and there is no client-side request that can
 * fail silently (see CP-36, #408).
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class RecordModuleController
{
    /**
     * Upper bound for the filter preset tiles' counts (CP-33 follow-up). Not a pagination page
     * size - a large cap so each figure is exact for realistic workloads, while
     * {@see \Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\PaginatedResult::$hasMore} still
     * gives an honest "at least N" signal for the rare case a preset matches more records than
     * this covers. Same reasoning and value as {@see \Xima\XimaTypo3ContentPlanner\Widgets\ContentStatusWidget::ASSIGNEE_COUNT_LIMIT}.
     */
    private const PRESET_COUNT_LIMIT = 999;

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private UriBuilder $uriBuilder,
        private RecordRepository $recordRepository,
        private StatusRepository $statusRepository,
        private BackendUserRepository $backendUserRepository,
        private WatcherService $watcherService,
        private PageRenderer $pageRenderer,
    ) {}

    /**
     * @throws Exception
     */
    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!PermissionUtility::checkContentStatusVisibility()) {
            return new HtmlResponse('', 403);
        }

        $this->pageRenderer->addCssFile('EXT:xima_typo3_content_planner/Resources/Public/Css/RecordModule.css');
        $this->pageRenderer->addCssFile('EXT:xima_typo3_content_planner/Resources/Public/Css/Status.css');

        $filter = $this->parseFilter($request->getQueryParams());
        $page = max(1, (int) ($request->getQueryParams()['page'] ?? 1));
        $offset = ($page - 1) * RecordRepository::DEFAULT_PAGE_SIZE;
        $backendUserId = $this->getBackendUserId();

        $watchedRecords = $filter['watched'] ? $this->watcherService->getWatchedRecords($backendUserId) : null;
        $effectiveSort = $filter['sort'] ?? 'changed';
        $effectiveDirection = $filter['dir'] ?? 'desc';

        $filterResult = $this->recordRepository->findAllByFilter(
            $filter['search'],
            $filter['status'],
            $filter['assignee'],
            $filter['type'],
            $filter['todo'],
            RecordRepository::DEFAULT_PAGE_SIZE,
            $filter['openComments'],
            $watchedRecords,
            $offset,
            $effectiveSort,
            $effectiveDirection,
        );

        $items = array_map(
            fn (array $record): array => $this->addFilterLinks(StatusItem::create($record)->toArray()),
            $filterResult->items,
        );

        $statusOptions = $this->statusRepository->findAll();
        $userOptions = $this->backendUserRepository->findAll();

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle($this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/Modules/records.xlf:title'));
        $moduleTemplate->assignMultiple([
            'items' => $items,
            'hasMore' => $filterResult->hasMore,
            'page' => $page,
            'rangeStart' => [] === $items ? 0 : $offset + 1,
            'rangeEnd' => $offset + count($items),
            'filter' => $filter,
            'sortLinks' => $this->buildSortLinks($filter, $effectiveSort, $effectiveDirection),
            'presets' => $this->buildPresetTiles($filter, $backendUserId),
            'formUrl' => $this->buildUrl([]),
            'previousPageUrl' => $page > 1 ? $this->buildUrl($filter, $page - 1) : null,
            'nextPageUrl' => $filterResult->hasMore ? $this->buildUrl($filter, $page + 1) : null,
            'statusOptions' => $statusOptions,
            'userOptions' => $userOptions,
            'typeOptions' => $this->buildTypeOptions(),
        ]);

        return $moduleTemplate->renderResponse('Backend/Modules/Records/Index');
    }

    /**
     * Row-level quick filters (CP-33 follow-up): clicking a row's status/type/assignee jumps
     * into the same list filtered by just that value, the same single-dimension `buildUrl()`
     * pattern the preset tiles above the list already use. Only set when that dimension carries
     * a real value - a record with no assignee has nothing to filter by.
     *
     * @param array<string, mixed> $item {@see StatusItem::toArray()}
     *
     * @return array<string, mixed>
     */
    private function addFilterLinks(array $item): array
    {
        $item['statusFilterLink'] = $this->buildConditionalFilterLink('status', (int) ($item['data'][Configuration::FIELD_STATUS] ?? 0));
        $item['typeFilterLink'] = $this->buildUrl(['type' => $item['data']['tablename']]);
        $item['assigneeFilterLink'] = $this->buildConditionalFilterLink('assignee', (int) ($item['data'][Configuration::FIELD_ASSIGNEE] ?? 0));

        return $item;
    }

    /**
     * A record with no status/assignee (uid 0) has nothing to filter by - buildUrl() would
     * otherwise happily build a "status=0"/"assignee=0" link that just returns an empty list.
     */
    private function buildConditionalFilterLink(string $key, int $uid): ?string
    {
        return $uid > 0 ? $this->buildUrl([$key => $uid]) : null;
    }

    /**
     * Column header sort links (CP-33 follow-up): clicking a sortable header sorts by it,
     * clicking the already-active one flips direction. Only Title and Changed are sortable -
     * see {@see RecordRepository::SORTABLE_FIELDS}
     * for why Status/Assignee/Site are not.
     *
     * @param array{search: ?string, status: ?int, assignee: ?int, type: ?string, todo: bool, openComments: bool, watched: bool, sort: ?string, dir: ?string} $filter
     *
     * @return array<string, array{link: string, activeDirection: ?string, ariaSort: ?string}>
     */
    private function buildSortLinks(array $filter, string $effectiveSort, string $effectiveDirection): array
    {
        $defaultDirections = ['title' => 'asc', 'changed' => 'desc'];
        $links = [];

        foreach ($defaultDirections as $field => $defaultDirection) {
            $isActive = $effectiveSort === $field;
            $nextDirection = $isActive ? $this->flipDirection($effectiveDirection) : $defaultDirection;

            $links[$field] = [
                'link' => $this->buildUrl(['sort' => $field, 'dir' => $nextDirection] + $filter),
                'activeDirection' => $isActive ? $effectiveDirection : null,
                'ariaSort' => $isActive ? ('asc' === $effectiveDirection ? 'ascending' : 'descending') : null,
            ];
        }

        return $links;
    }

    private function flipDirection(string $direction): string
    {
        return 'asc' === $direction ? 'desc' : 'asc';
    }

    /**
     * @param array<string, mixed> $queryParams
     *
     * @return array{search: ?string, status: ?int, assignee: ?int, type: ?string, todo: bool, openComments: bool, watched: bool, sort: ?string, dir: ?string}
     */
    private function parseFilter(array $queryParams): array
    {
        return [
            'search' => array_key_exists('search', $queryParams) ? (string) $queryParams['search'] : null,
            'status' => array_key_exists('status', $queryParams) && '' !== $queryParams['status'] ? (int) $queryParams['status'] : null,
            'assignee' => array_key_exists('assignee', $queryParams) && '' !== $queryParams['assignee'] ? (int) $queryParams['assignee'] : null,
            'type' => array_key_exists('type', $queryParams) && '' !== $queryParams['type'] ? (string) $queryParams['type'] : null,
            'todo' => (bool) ($queryParams['todo'] ?? false),
            'openComments' => (bool) ($queryParams['openComments'] ?? false),
            'watched' => (bool) ($queryParams['watched'] ?? false),
            // Kept null (rather than defaulting here to "changed"/"desc") so a link/URL built
            // from $filter stays clean until the user actually picks a sort - see buildUrl().
            'sort' => $this->parseEnumParam($queryParams, 'sort', ['title', 'changed']),
            'dir' => $this->parseEnumParam($queryParams, 'dir', ['asc', 'desc']),
        ];
    }

    /**
     * @param array<string, mixed> $queryParams
     * @param list<string>         $allowedValues
     */
    private function parseEnumParam(array $queryParams, string $key, array $allowedValues): ?string
    {
        return array_key_exists($key, $queryParams) && in_array($queryParams[$key], $allowedValues, true)
            ? $queryParams[$key]
            : null;
    }

    /**
     * Quick-access filter presets shown above the list: each reuses findAllByFilter() the same
     * way the dashboard's assignee KPI tile does
     * ({@see \Xima\XimaTypo3ContentPlanner\Widgets\ContentStatusWidget::buildAssigneeInfo()}),
     * one dimension at a time, so a preset's count can never disagree with what clicking it
     * actually shows. A pressed preset links to the list without it, so the chip toggles.
     *
     * @param array{search: ?string, status: ?int, assignee: ?int, type: ?string, todo: bool, openComments: bool, watched: bool, sort: ?string, dir: ?string} $filter
     *
     * @return array<string, array{count: int, countLabel: string, link: string, active: bool}>
     *
     * @throws Exception
     */
    private function buildPresetTiles(array $filter, int $backendUserId): array
    {
        return [
            'assignee' => $this->buildPresetTile($backendUserId, null, false, 'assignee', $backendUserId, $filter, $filter['assignee'] === $backendUserId),
            'todo' => $this->buildPresetTile(null, true, false, 'todo', 1, $filter, $filter['todo']),
            'openComments' => $this->buildPresetTile(null, null, true, 'openComments', 1, $filter, $filter['openComments']),
        ];
    }

    /**
     * @param array<string, mixed> $filter
     *
     * @return array{count: int, countLabel: string, link: string, active: bool}
     *
     * @throws Exception
     */
    private function buildPresetTile(?int $assignee, ?bool $todo, bool $openComments, string $filterKey, int $filterValue, array $filter, bool $active): array
    {
        $result = $this->recordRepository->findAllByFilter(null, null, $assignee, null, $todo, self::PRESET_COUNT_LIMIT, $openComments);
        $count = count($result->items);

        return [
            'count' => $count,
            'countLabel' => $result->hasMore ? $count.'+' : (string) $count,
            'link' => $active ? $this->buildUrl([$filterKey => null] + $filter) : $this->buildUrl([$filterKey => $filterValue]),
            'active' => $active,
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function buildTypeOptions(): array
    {
        $recordTables = ExtensionUtility::getRecordTables();
        if (count($recordTables) <= 1) {
            return [];
        }

        return array_map(
            fn (string $table): array => ['label' => $this->getLanguageService()->sL($GLOBALS['TCA'][$table]['ctrl']['title'] ?? $table), 'value' => $table],
            $recordTables,
        );
    }

    /**
     * @param array<string, mixed> $filter
     */
    private function buildUrl(array $filter, int $page = 1): string
    {
        $params = array_filter($filter, static fn (mixed $value): bool => null !== $value && false !== $value && '' !== $value);
        if ($page > 1) {
            $params['page'] = $page;
        }

        return (string) $this->uriBuilder->buildUriFromRoute('content_planner_records', $params);
    }

    private function getBackendUserId(): int
    {
        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];

        return $backendUser->getUserId();
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
