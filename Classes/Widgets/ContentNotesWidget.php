<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets;

use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentNotesWidget extends AbstractWidget
{
    public function renderWidgetContent(): string
    {
        /** @var PageRenderer $pageRenderer */
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/detail-modal.js');
        return $this->render(
            'EXT:xima_typo3_content_planner/Resources/Private/Templates/Backend/ContentNotes.html',
            [
                'configuration' => $this->configuration,
                'records' => $this->dataProvider->getItems(),
                'options' => $this->options,
                'button' => $this->buttonProvider,
                'icon' => 'content-thumbtack',
            ]
        );
    }
}
