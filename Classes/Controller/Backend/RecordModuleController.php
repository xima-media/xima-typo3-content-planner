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
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\StatusItem;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Service\WatcherService;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function array_filter;
use function array_key_exists;
use function array_map;
use function count;

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
    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private UriBuilder $uriBuilder,
        private RecordRepository $recordRepository,
        private StatusRepository $statusRepository,
        private BackendUserRepository $backendUserRepository,
        private WatcherService $watcherService,
    ) {}

    /**
     * @throws Exception
     */
    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!PermissionUtility::checkContentStatusVisibility()) {
            return new HtmlResponse('', 403);
        }

        $filter = $this->parseFilter($request->getQueryParams());
        $page = max(1, (int) ($request->getQueryParams()['page'] ?? 1));
        $offset = ($page - 1) * RecordRepository::DEFAULT_PAGE_SIZE;

        $watchedRecords = $filter['watched'] ? $this->watcherService->getWatchedRecords($this->getBackendUserId()) : null;

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
            static fn (array $record): array => StatusItem::create($record)->toArray(),
            $filterResult->items,
        );

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle($this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/Modules/records.xlf:title'));
        $moduleTemplate->assignMultiple([
            'items' => $items,
            'hasMore' => $filterResult->hasMore,
            'page' => $page,
            'filter' => $filter,
            'formUrl' => $this->buildUrl([]),
            'previousPageUrl' => $page > 1 ? $this->buildUrl($filter, $page - 1) : null,
            'nextPageUrl' => $filterResult->hasMore ? $this->buildUrl($filter, $page + 1) : null,
            'statusOptions' => $this->statusRepository->findAll(),
            'userOptions' => $this->backendUserRepository->findAll(),
            'typeOptions' => $this->buildTypeOptions(),
        ]);

        return $moduleTemplate->renderResponse('Backend/Modules/Records/Index');
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
