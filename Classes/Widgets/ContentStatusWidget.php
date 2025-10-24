<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Widgets;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Utility\{ExtensionUtility, IconHelper};
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
        $mode = 'status';
        $assignee = null;
        $todo = false;
        $filterValues = false;

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

        /** @var ContentStatusDataProvider $dataProvider */
        $dataProvider = $this->dataProvider;
        if ($filter) {
            $filterValues = [
                'status' => $dataProvider->getStatus(),
                'users' => $dataProvider->getUsers(),
            ];
            $recordTables = ExtensionUtility::getRecordTables();
            if (count($recordTables) > 1) {
                $recordTables = array_map(function ($table) {
                    return ['label' => $this->getLanguageService()->sL($GLOBALS['TCA'][$table]['ctrl']['title']), 'value' => $table];
                }, $recordTables);
                $filterValues['types'] = $recordTables;
            }
        }

        if ($todo && ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_COMMENT_TODOS)) {
            $commentRepository = GeneralUtility::makeInstance(CommentRepository::class);
            $todoResolved = $commentRepository->countTodoAllByRecord(allRecords: true);
            $todoTotal = $commentRepository->countTodoAllByRecord(todoField: 'todo_total', allRecords: true);

            $todo = $todoTotal > 0 ? sprintf(
                '%s <span class="xima-typo3-content-planner--comment-todo badge" data-status="%s">%d/%d</span>',
                IconHelper::getIconByIdentifier('actions-check-square'),
                $todoResolved === $todoTotal ? 'resolved' : 'pending',
                $todoResolved,
                $todoTotal,
            ) : '';
        }

        return $this->render(
            'Backend/Widgets/ContentStatusList.html',
            [
                'configuration' => $this->configuration,
                'options' => $this->options,
                'icon' => $icon,
                'currentBackendUser' => $assignee,
                'todo' => $todo,
                'mode' => $mode,
                'filter' => $filterValues,
            ],
        );
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
