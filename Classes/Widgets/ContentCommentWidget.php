<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets;

class ContentCommentWidget extends AbstractWidget
{
    public function renderWidgetContent(): string
    {
        return $this->render(
            'EXT:xima_typo3_content_planner/Resources/Private/Templates/Backend/Widgets/ContentCommentList.html',
            [
                'configuration' => $this->configuration,
                'records' => $this->dataProvider->getItems(),
                'options' => $this->options,
                'icon' => 'content-message',
            ]
        );
    }
}
