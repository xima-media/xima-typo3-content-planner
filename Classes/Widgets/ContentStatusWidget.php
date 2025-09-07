<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "xima_typo3_content_planner".
 *
 * Copyright (C) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Xima\XimaTypo3ContentPlanner\Widgets;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\IconHelper;
use Xima\XimaTypo3ContentPlanner\Widgets\Provider\ContentStatusDataProvider;

/**
 * ContentStatusWidget.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
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
            $assignee !== null && $assignee > 0 => 'status-user-backend',
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
                $todoTotal
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
            ]
        );
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
