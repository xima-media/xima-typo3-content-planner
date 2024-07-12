<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets;

use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

class ContentStatusWidget extends AbstractWidget
{
    public function renderWidgetContent(): string
    {
        $filter = isset($this->options['useFilter']) ?: false;
        $assignee = isset($this->options['currentUserAssignee']) ? $GLOBALS['BE_USER']->getUserId() : null;
        $icon = $assignee ? 'status-user-backend' : 'flag-gray';
        $filterValues = false;
        if ($filter) {
            $filterValues = [
                'status' => $this->dataProvider->getStatus(),
                'users' => $this->dataProvider->getUsers(),
            ];
            $recordTables = ExtensionUtility::getRecordTables();
            if (count($recordTables) > 1) {
                $recordTables = array_map(function ($table) {
                    return ['label' => $this->getLanguageService()->sL($GLOBALS['TCA'][$table]['ctrl']['title']), 'value' => $table];
                }, $recordTables);
                $filterValues['types'] = $recordTables;
            }
        }

        return $this->render(
            'EXT:xima_typo3_content_planner/Resources/Private/Templates/Backend/Widgets/ContentStatusList.html',
            [
                'configuration' => $this->configuration,
                'options' => $this->options,
                'icon' => $icon,
                'currentBackendUser' => $assignee,
                'filter' => $filterValues,
            ]
        );
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
