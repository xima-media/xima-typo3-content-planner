<?php

declare(strict_types=1);

namespace Xima\XimaContentPlanner\Widgets;

class ContentNoteWidget extends AbstractWidget
{
    public function renderWidgetContent(): string
    {
        return $this->render(
            'EXT:xima_content_planner/Resources/Private/Templates/Backend/ContentNote.html',
            [
                'configuration' => $this->configuration,
                'record' => $this->dataProvider->getItem(),
                'options' => $this->options,
                'icon' => 'content-thumbtack',
            ]
        );
    }
}
