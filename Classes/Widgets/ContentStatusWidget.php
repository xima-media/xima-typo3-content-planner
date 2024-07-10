<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets;

class ContentStatusWidget extends AbstractWidget
{
    public function renderWidgetContent(): string
    {
        $filter = isset($this->options['useFilter']) ?: false;
        $assignee = isset($this->options['currentUserAssignee']) ? $GLOBALS['BE_USER']->getUserId() : null;
        $maxResults = isset($this->options['maxResults']) ? (int)$this->options['maxResults'] : 20;
        $icon = $assignee ? 'status-user-backend' : 'flag-gray';

        return $this->render(
            'EXT:xima_typo3_content_planner/Resources/Private/Templates/Backend/Widgets/ContentStatusList.html',
            [
                'configuration' => $this->configuration,
                'records' => $this->dataProvider->getItemsByDemand(assignee: $assignee, maxResults: $maxResults),
                'options' => $this->options,
                'icon' => $icon,
                'currentBackendUser' => $assignee,
                'filter' => $filter ? [
                    'status' => $this->dataProvider->getStatus(),
                    'users' => $this->dataProvider->getUsers(),
                ] : false,
            ]
        );
    }
}
