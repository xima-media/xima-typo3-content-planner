<?php

declare(strict_types=1);

namespace Xima\XimaContentPlanner\Widgets;

class ContentUpdateWidget extends AbstractWidget
{
    public function renderWidgetContent(): string
    {
        return $this->render(
            'EXT:xima_content_planner/Resources/Private/Templates/Backend/ContentUpdateList.html',
            [
                'configuration' => $this->configuration,
                'records' => $this->dataProvider->getItems(),
                'options' => $this->options,
                'icon' => 'actions-refresh',
            ]
        );
    }
}
