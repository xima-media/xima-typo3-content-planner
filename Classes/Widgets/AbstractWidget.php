<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\AdditionalCssInterface;
use TYPO3\CMS\Dashboard\Widgets\ButtonProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\JavaScriptInterface;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Xima\XimaTypo3ContentPlanner\Configuration;

abstract class AbstractWidget implements WidgetInterface, AdditionalCssInterface, JavaScriptInterface
{
    protected ServerRequestInterface $request;

    public function __construct(
        protected readonly WidgetConfigurationInterface $configuration,
        protected readonly ListDataProviderInterface $dataProvider,
        protected readonly ?ButtonProviderInterface $buttonProvider = null,
        protected readonly array $buttons = [],
        protected array $options = []
    ) {
    }

    public function render(string $templateFile, array $templateArguments): string
    {
        $template = GeneralUtility::getFileAbsFileName($templateFile);

        // preparing view
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setFormat('html');
        $view->setTemplateRootPaths(['EXT:' . Configuration::EXT_KEY . '/Resources/Private/Templates/']);
        $view->setPartialRootPaths(['EXT:' . Configuration::EXT_KEY . '/Resources/Private/Partials/']);
        $view->setTemplatePathAndFilename($template);

        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addInlineLanguageLabelFile('EXT:ximatypo3contentplanner/Resources/Private/Language/locallang.xlf');

        $view->assignMultiple($templateArguments);
        return $view->render();
    }

    abstract public function renderWidgetContent(): string;

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getCssFiles(): array
    {
        return ['EXT:' . Configuration::EXT_KEY . '/Resources/Public/Css/Widgets.css'];
    }

    public function getJavaScriptModuleInstructions(): array
    {
        return [
            JavaScriptModuleInstruction::create('@xima/ximatypo3contentplanner/filter-status.js'),
            JavaScriptModuleInstruction::create('@xima/ximatypo3contentplanner/comments-modal.js'),
        ];
    }
}
