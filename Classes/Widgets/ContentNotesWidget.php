<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets;

class ContentNotesWidget extends AbstractWidget
{
    public function renderWidgetContent(): string
    {
        return $this->render(
            'EXT:xima_typo3_content_planner/Resources/Private/Templates/Backend/Widgets/ContentNotes.html',
            [
                'configuration' => $this->configuration,
                'records' => $this->dataProvider->getItems(),
                'options' => $this->options,
                'button' => $GLOBALS['BE_USER']->check('tables_modify', 'tx_ximatypo3contentplanner_note') ? $this->buttonProvider : null,
                'icon' => 'content-thumbtack',
            ]
        );
    }
}
