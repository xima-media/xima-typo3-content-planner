<?php

declare(strict_types=1);

namespace Xima\XimaContentPlanner\Widgets;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\ButtonProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Xima\XimaContentPlanner\Configuration;

class ContentStatusWidget implements WidgetInterface
{
    protected ServerRequestInterface $request;

    public function __construct(
        protected readonly WidgetConfigurationInterface $configuration,
        protected readonly ListDataProviderInterface $dataProvider,
        protected readonly ?ButtonProviderInterface $buttonProvider = null,
        protected array $options = []
    ) {
    }

    public function renderWidgetContent(): string
    {
        $template = GeneralUtility::getFileAbsFileName('EXT:xima_content_planner/Resources/Private/Templates/Backend/ContentStatusList.html');

        // preparing view
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setFormat('html');
        $view->setTemplateRootPaths(['EXT:xima_content_planner/Resources/Private/Templates/']);
        $view->setPartialRootPaths(['EXT:xima_content_planner/Resources/Private/Partials/']);
        $view->setTemplatePathAndFilename($template);

        $status = isset($this->options['status']) ? $this->options['status'] : null;
        $assignee = isset($this->options['currentUserAssignee']) ? $GLOBALS['BE_USER']->getUserId() : null;
        $maxResults = isset($this->options['maxResults']) ? (int)$this->options['maxResults'] : 20;
        $icon = $status ? Configuration::STATUS_ICONS[$status] : 'status-user-backend';

        $view->assignMultiple([
            'configuration' => $this->configuration,
            'records' => $this->dataProvider->getItemsByDemand($status, $assignee, $maxResults),
            'options' => $this->options,
            'icon' => $icon,
            'highlight' => $assignee ? true : false,
        ]);
        return $view->render();
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
