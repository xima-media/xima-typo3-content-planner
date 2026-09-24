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
use function sprintf;

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

        $filter = $this->parseFilter($request->getQueryParams());
        $page = max(1, (int) ($request->getQueryParams()['page'] ?? 1));
        $offset = ($page - 1) * RecordRepository::DEFAULT_PAGE_SIZE;
        $backendUserId = $this->getBackendUserId();

        $watchedRecords = $filter['watched'] ? $this->watcherService->getWatchedRecords($backendUserId) : null;

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
            'advancedFilterCount' => $this->countActiveAdvancedFilters($filter),
            'activeFilterChips' => $this->buildActiveFilterChips($filter, $statusOptions, $userOptions),
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
        $statusUid = (int) ($item['data'][Configuration::FIELD_STATUS] ?? 0);
        $item['statusFilterLink'] = $statusUid > 0 ? $this->buildUrl(['status' => $statusUid]) : null;

        $item['typeFilterLink'] = $this->buildUrl(['type' => $item['data']['tablename']]);

        $assigneeUid = (int) ($item['data'][Configuration::FIELD_ASSIGNEE] ?? 0);
        $item['assigneeFilterLink'] = $assigneeUid > 0 ? $this->buildUrl(['assignee' => $assigneeUid]) : null;

        return $item;
    }

    /**
     * @param array<string, mixed> $queryParams
     *
     * @return array{search: ?string, status: ?int, assignee: ?int, type: ?string, todo: bool, openComments: bool, watched: bool}
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
        ];
    }

    /**
     * @param array{search: ?string, status: ?int, assignee: ?int, type: ?string, todo: bool, openComments: bool, watched: bool} $filter
     */
    private function countActiveAdvancedFilters(array $filter): int
    {
        // "search" lives in the always-visible primary row; "todo"/"openComments" moved to the
        // preset tiles above the list, which carry their own active state - neither belongs in
        // the count that badges/auto-expands the collapsible advanced panel below.
        return count(array_filter([
            $filter['status'],
            $filter['assignee'],
            $filter['type'],
            $filter['watched'],
        ], static fn (mixed $value): bool => null !== $value && false !== $value));
    }

    /**
     * Quick-access filter presets shown above the list (CP-33 follow-up): each reuses
     * findAllByFilter() the same way the dashboard's assignee KPI tile does
     * ({@see \Xima\XimaTypo3ContentPlanner\Widgets\ContentStatusWidget::buildAssigneeInfo()}),
     * one dimension at a time, so a preset's count can never disagree with what clicking it
     * actually shows.
     *
     * @param array{search: ?string, status: ?int, assignee: ?int, type: ?string, todo: bool, openComments: bool, watched: bool} $filter
     *
     * @return array<string, array{count: int, countLabel: string, link: string, active: bool}>
     *
     * @throws Exception
     */
    private function buildPresetTiles(array $filter, int $backendUserId): array
    {
        return [
            'assignee' => $this->buildPresetTile(null, null, $backendUserId, null, null, false, ['assignee' => $backendUserId], $filter['assignee'] === $backendUserId),
            'todo' => $this->buildPresetTile(null, null, null, null, true, false, ['todo' => 1], $filter['todo']),
            'openComments' => $this->buildPresetTile(null, null, null, null, null, true, ['openComments' => 1], $filter['openComments']),
        ];
    }

    /**
     * @param array<string, mixed> $urlParams
     *
     * @return array{count: int, countLabel: string, link: string, active: bool}
     *
     * @throws Exception
     */
    private function buildPresetTile(?string $search, ?int $status, ?int $assignee, ?string $type, ?bool $todo, bool $openComments, array $urlParams, bool $active): array
    {
        $result = $this->recordRepository->findAllByFilter($search, $status, $assignee, $type, $todo, self::PRESET_COUNT_LIMIT, $openComments);
        $count = count($result->items);

        return [
            'count' => $count,
            'countLabel' => $result->hasMore ? $count.'+' : (string) $count,
            'link' => $this->buildUrl($urlParams),
            'active' => $active,
        ];
    }

    /**
     * Dismissable summary of the currently active filters (CP-33 follow-up), shown below the
     * filter row: each chip's link is the current filter with just that one dimension cleared,
     * built via buildUrl() like every other link this controller generates. `search`/`todo`/
     * `openComments` are included here too, even though they already have their own visible
     * affordance elsewhere (the search field, the preset tiles) - the chip row is meant to be a
     * single, complete "what's currently filtering this list" summary, not just the fields the
     * advanced panel owns.
     *
     * @param array{search: ?string, status: ?int, assignee: ?int, type: ?string, todo: bool, openComments: bool, watched: bool} $filter
     * @param list<Status>                                                                                                       $statusOptions
     * @param list<array<string, mixed>>                                                                                         $userOptions
     *
     * @return list<array{label: string, removeUrl: string}>
     */
    private function buildActiveFilterChips(array $filter, array $statusOptions, array $userOptions): array
    {
        $languageService = $this->getLanguageService();
        $chips = [];

        if (null !== $filter['search'] && '' !== $filter['search']) {
            $chips[] = [
                'label' => sprintf($languageService->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang.xlf:records.chips.search'), $filter['search']),
                'removeUrl' => $this->buildUrl(['search' => null] + $filter),
            ];
        }

        if (null !== $filter['status']) {
            $statusTitle = $this->findStatusTitle($statusOptions, $filter['status']) ?? (string) $filter['status'];
            $chips[] = [
                'label' => sprintf($languageService->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang.xlf:records.chips.status'), $statusTitle),
                'removeUrl' => $this->buildUrl(['status' => null] + $filter),
            ];
        }

        if (null !== $filter['assignee']) {
            $username = $this->findLabel($userOptions, 'uid', 'username', $filter['assignee']) ?? (string) $filter['assignee'];
            $chips[] = [
                'label' => sprintf($languageService->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang.xlf:records.chips.assignee'), $username),
                'removeUrl' => $this->buildUrl(['assignee' => null] + $filter),
            ];
        }

        if (null !== $filter['type']) {
            $typeLabel = $this->getLanguageService()->sL($GLOBALS['TCA'][$filter['type']]['ctrl']['title'] ?? $filter['type']);
            $chips[] = [
                'label' => sprintf($languageService->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang.xlf:records.chips.type'), $typeLabel),
                'removeUrl' => $this->buildUrl(['type' => null] + $filter),
            ];
        }

        if ($filter['todo']) {
            $chips[] = [
                'label' => $languageService->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang.xlf:filter.openTodos'),
                'removeUrl' => $this->buildUrl(['todo' => false] + $filter),
            ];
        }

        if ($filter['openComments']) {
            $chips[] = [
                'label' => $languageService->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang.xlf:filter.openComments'),
                'removeUrl' => $this->buildUrl(['openComments' => false] + $filter),
            ];
        }

        if ($filter['watched']) {
            $chips[] = [
                'label' => $languageService->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang.xlf:filter.watchedByMe'),
                'removeUrl' => $this->buildUrl(['watched' => false] + $filter),
            ];
        }

        return $chips;
    }

    /**
     * @param list<Status> $statusOptions
     */
    private function findStatusTitle(array $statusOptions, int $uid): ?string
    {
        foreach ($statusOptions as $status) {
            if ($status->getUid() === $uid) {
                return $status->getTitle();
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $options
     */
    private function findLabel(array $options, string $keyField, string $labelField, int $value): ?string
    {
        foreach ($options as $option) {
            if ((int) $option[$keyField] === $value) {
                return (string) $option[$labelField];
            }
        }

        return null;
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
