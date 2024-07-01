<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets;

use Xima\XimaTypo3ContentPlanner\Configuration;

class ContentStatusWidget extends AbstractWidget
{
    public function renderWidgetContent(): string
    {
        $status = isset($this->options['status']) ? $this->options['status'] : null;
        $assignee = isset($this->options['currentUserAssignee']) ? $GLOBALS['BE_USER']->getUserId() : null;
        $maxResults = isset($this->options['maxResults']) ? (int)$this->options['maxResults'] : 20;
        $icon = $status ? Configuration::STATUS_ICONS[$status] : 'status-user-backend';
        return $this->render(
            'EXT:xima_typo3_content_planner/Resources/Private/Templates/Backend/ContentStatusList.html',
            [
                'configuration' => $this->configuration,
                'records' => $this->dataProvider->getItemsByDemand($status, $assignee, $maxResults),
                'options' => $this->options,
                'icon' => $icon,
            ]
        );
    }
}
