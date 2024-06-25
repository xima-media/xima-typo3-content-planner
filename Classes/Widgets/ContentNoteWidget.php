<?php

declare(strict_types=1);

namespace Xima\XimaContentPlanner\Widgets;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\ButtonProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Xima\XimaContentPlanner\Widgets\Provider\ContentNoteDataProvider;

class ContentNoteWidget implements WidgetInterface
{
    protected ServerRequestInterface $request;

    public function __construct(
        protected readonly WidgetConfigurationInterface $configuration,
        protected readonly ContentNoteDataProvider $dataProvider,
        protected readonly ?ButtonProviderInterface $buttonProvider = null,
        protected array $options = []
    ) {
    }

    public function renderWidgetContent(): string
    {
        $template = GeneralUtility::getFileAbsFileName('EXT:xima_content_planner/Resources/Private/Templates/Backend/ContentNote.html');

        // preparing view
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setFormat('html');
        $view->setTemplateRootPaths(['EXT:xima_content_planner/Resources/Private/Templates/']);
        $view->setPartialRootPaths(['EXT:xima_content_planner/Resources/Private/Partials/']);
        $view->setTemplatePathAndFilename($template);
        $view->assignMultiple([
            'configuration' => $this->configuration,
            'record' => $this->dataProvider->getItem(),
            'options' => $this->options,
            'icon' => 'content-thumbtack',
        ]);
        return $view->render();
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
