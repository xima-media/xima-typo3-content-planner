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

namespace Xima\XimaTypo3ContentPlanner\Widgets;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\{ButtonProviderInterface, ListDataProviderInterface, WidgetConfigurationInterface};
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Widgets\Provider\ContentStatusDataProvider;

use function count;

/**
 * ContentStatusWidget.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ContentStatusWidget extends AbstractWidget
{
    /**
     * Upper bound for the assignee KPI tile's count query (CP-33, #405). Not a pagination page
     * size like {@see RecordRepository::DEFAULT_PAGE_SIZE} - a large cap so the figure is exact
     * for realistic workloads, while {@see PaginatedResult::$hasMore} still gives an honest "at
     * least N" signal for the rare case a single user has more records assigned than this covers.
     */
    private const ASSIGNEE_COUNT_LIMIT = 999;

    /**
     * @param array<string, mixed> $buttons
     * @param array<string, mixed> $options
     */
    public function __construct(
        WidgetConfigurationInterface $configuration,
        ListDataProviderInterface $dataProvider,
        private readonly RecordRepository $recordRepository,
        private readonly UriBuilder $uriBuilder,
        ?ButtonProviderInterface $buttonProvider = null,
        array $buttons = [],
        array $options = [],
    ) {
        parent::__construct($configuration, $dataProvider, $buttonProvider, $buttons, $options);
    }

    public function renderWidgetContent(): string
    {
        $filter = isset($this->options['useFilter']);
        ['mode' => $mode, 'assignee' => $assignee, 'todo' => $todo, 'icon' => $icon] = $this->determineWidgetMode();

        $filterValues = $filter ? $this->buildFilterValues() : false;
        $todoInfo = $todo ? $this->buildTodoInfo() : null;
        $assigneeInfo = (null !== $assignee && $assignee > 0) ? $this->buildAssigneeInfo($assignee) : null;

        return $this->render(
            'Backend/Widgets/ContentStatusList.html',
            [
                'configuration' => $this->configuration,
                'options' => $this->options,
                'icon' => $icon,
                'currentBackendUser' => $assignee,
                'backendUserId' => $this->getBackendUserId(),
                'todo' => $todo,
                'todoInfo' => $todoInfo,
                'assigneeInfo' => $assigneeInfo,
                'mode' => $mode,
                'filter' => $filterValues,
            ],
        );
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    private function getBackendUserId(): int
    {
        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];

        return $backendUser->getUserId();
    }

    /**
     * @return array{mode: string, assignee: int|null, todo: bool, icon: string}
     */
    private function determineWidgetMode(): array
    {
        $mode = 'status';
        $assignee = null;
        $todo = false;

        if (isset($this->options['currentUserAssignee'])) {
            $assignee = $this->getBackendUserId();
            $mode = 'assignee';
        }

        if (isset($this->options['todo'])) {
            $todo = true;
            $mode = 'todo';
        }

        $icon = match (true) {
            null !== $assignee && $assignee > 0 => 'status-user-backend',
            $todo => 'form-multi-checkbox',
            default => 'flag-gray',
        };

        return ['mode' => $mode, 'assignee' => $assignee, 'todo' => $todo, 'icon' => $icon];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private function buildFilterValues(): array
    {
        /** @var ContentStatusDataProvider $dataProvider */
        $dataProvider = $this->dataProvider;
        $filterValues = [
            'status' => $dataProvider->getStatus(),
            'users' => $dataProvider->getUsers(),
        ];

        $recordTables = ExtensionUtility::getRecordTables();
        if (count($recordTables) > 1) {
            $recordTables = array_map(fn ($table) => ['label' => $this->getLanguageService()->sL($GLOBALS['TCA'][$table]['ctrl']['title']), 'value' => $table], $recordTables);
            $filterValues['types'] = $recordTables;
        }

        return $filterValues;
    }

    /**
     * @return array{resolved: int, total: int, link: string}|null null when the todo feature is
     *                                                             disabled ext-wide (see CP-33, #405
     *                                                             zero state: 0 open tasks reads the
     *                                                             same as the feature being off)
     */
    private function buildTodoInfo(): ?array
    {
        if (!ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_COMMENT_TODOS)) {
            return null;
        }

        $commentRepository = GeneralUtility::makeInstance(CommentRepository::class);
        $todoResolved = $commentRepository->countTodoAllByRecord(null, null, 'todo_resolved', true);
        $todoTotal = $commentRepository->countTodoAllByRecord(null, null, 'todo_total', true);

        return [
            'resolved' => $todoResolved,
            'total' => $todoTotal,
            'link' => $this->buildRecordsLink(['todo' => 1]),
        ];
    }

    /**
     * @return array{count: int, hasMore: bool, link: string}
     *
     * @throws Exception
     */
    private function buildAssigneeInfo(int $userId): array
    {
        $result = $this->recordRepository->findAllByFilter(null, null, $userId, null, null, self::ASSIGNEE_COUNT_LIMIT);

        return [
            'count' => count($result->items),
            'hasMore' => $result->hasMore,
            'link' => $this->buildRecordsLink(['assignee' => $userId]),
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildRecordsLink(array $params): string
    {
        return (string) $this->uriBuilder->buildUriFromRoute('content_planner_records', $params);
    }
}
