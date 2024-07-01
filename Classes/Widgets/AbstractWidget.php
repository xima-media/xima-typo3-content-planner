<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\ButtonProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Xima\XimaTypo3ContentPlanner\Widgets\Provider\ContentNoteDataProvider;

abstract class AbstractWidget implements WidgetInterface
{
    protected ServerRequestInterface $request;

    public function __construct(
        protected readonly WidgetConfigurationInterface $configuration,
        protected readonly ListDataProviderInterface|ContentNoteDataProvider $dataProvider,
        protected readonly ?ButtonProviderInterface $buttonProvider = null,
        protected array $options = []
    ) {
    }

    public function render(string $templateFile, array $templateArguments): string
    {
        $template = GeneralUtility::getFileAbsFileName($templateFile);

        // preparing view
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setFormat('html');
        $view->setTemplateRootPaths(['EXT:xima_typo3_content_planner/Resources/Private/Templates/']);
        $view->setPartialRootPaths(['EXT:xima_typo3_content_planner/Resources/Private/Partials/']);
        $view->setTemplatePathAndFilename($template);

        $view->assignMultiple($templateArguments);
        return $view->render();
    }

    abstract public function renderWidgetContent(): string;

    public function getOptions(): array
    {
        return $this->options;
    }
}
