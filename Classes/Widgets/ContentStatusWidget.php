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
use function sprintf;

/**
 * ContentStatusWidget.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ContentStatusWidget extends AbstractWidget
{
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
        $tiles = array_values(array_filter([
            (null !== $assignee && $assignee > 0) ? $this->buildAssigneeTile($assignee) : null,
            $todo ? $this->buildTodoTile() : null,
        ]));

        return $this->render(
            'Backend/Widgets/ContentStatusList.html',
            [
                'configuration' => $this->configuration,
                'options' => $this->options,
                'icon' => $icon,
                'currentBackendUser' => $assignee,
                'backendUserId' => $this->getBackendUserId(),
                'todo' => $todo,
                'tiles' => $tiles,
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

        if (isset($this->options['myWork'])) {
            $assignee = $this->getBackendUserId();
            $todo = true;
            $mode = 'mywork';
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
     * With the todo feature disabled ext-wide the tile reads as a zero state (CP-33, #405).
     *
     * @return array{link: string, icon: string, label: string, figure: string, summary: string}
     */
    private function buildTodoTile(): array
    {
        $resolved = 0;
        $total = 0;
        if (ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_COMMENT_TODOS)) {
            $commentRepository = GeneralUtility::makeInstance(CommentRepository::class);
            $resolved = $commentRepository->countTodoAllByRecord(null, null, 'todo_resolved', true);
            $total = $commentRepository->countTodoAllByRecord(null, null, 'todo_total', true);
        }

        return [
            'link' => $total > 0 && $resolved < $total ? $this->buildRecordsLink(['todo' => 1]) : '',
            'icon' => 'content-planner-checkbox',
            'label' => $this->translate('widgets.contentPlanner.kpi.todo'),
            'figure' => $total > 0 ? $resolved.'/'.$total : '0',
            'summary' => $total > 0
                ? sprintf($this->translate('widgets.contentPlanner.status.todo'), $resolved, $total)
                : $this->translate('widgets.contentPlanner.status.todo.empty'),
        ];
    }

    /**
     * @return array{link: string, icon: string, label: string, figure: string, summary: string}
     *
     * @throws Exception
     */
    private function buildAssigneeTile(int $userId): array
    {
        $count = $this->recordRepository->countVisibleByAssignee($userId);

        return [
            'link' => $count > 0 ? $this->buildRecordsLink(['assignee' => $userId]) : '',
            'icon' => 'content-planner-user-circle',
            'label' => $this->translate('widgets.contentPlanner.kpi.assignee'),
            'figure' => (string) $count,
            'summary' => $count > 0
                ? sprintf($this->translate('widgets.contentPlanner.status.assignee'), $count)
                : $this->translate('widgets.contentPlanner.status.assignee.empty'),
        ];
    }

    private function translate(string $key): string
    {
        return $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:'.$key);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildRecordsLink(array $params): string
    {
        return (string) $this->uriBuilder->buildUriFromRoute('content_planner_records', $params);
    }
}
