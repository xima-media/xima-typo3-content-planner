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
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\IconUtility;
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
    public function renderWidgetContent(): string
    {
        $filter = isset($this->options['useFilter']);
        ['mode' => $mode, 'assignee' => $assignee, 'todo' => $todo, 'icon' => $icon] = $this->determineWidgetMode();

        $filterValues = $filter ? $this->buildFilterValues() : false;
        $todoInfo = $todo ? $this->buildTodoInfo() : false;

        return $this->render(
            'Backend/Widgets/ContentStatusList.html',
            [
                'configuration' => $this->configuration,
                'options' => $this->options,
                'icon' => $icon,
                'currentBackendUser' => $assignee,
                'todo' => $todoInfo,
                'mode' => $mode,
                'filter' => $filterValues,
            ],
        );
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
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
            $assignee = $GLOBALS['BE_USER']->getUserId();
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

    private function buildTodoInfo(): string|bool
    {
        if (!ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_COMMENT_TODOS)) {
            return false;
        }

        $commentRepository = GeneralUtility::makeInstance(CommentRepository::class);
        $todoResolved = $commentRepository->countTodoAllByRecord(allRecords: true);
        $todoTotal = $commentRepository->countTodoAllByRecord(todoField: 'todo_total', allRecords: true);

        if ($todoTotal <= 0) {
            return '';
        }

        return sprintf(
            '%s <span class="content-planner-badge badge" data-status="%s">%d/%d</span>',
            IconUtility::getIconByIdentifier('actions-check-square'),
            $todoResolved === $todoTotal ? 'resolved' : 'pending',
            $todoResolved,
            $todoTotal,
        );
    }
}
