<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Widgets;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Page\{JavaScriptModuleInstruction, PageRenderer};
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\{AdditionalCssInterface, ButtonProviderInterface, JavaScriptInterface, ListDataProviderInterface, WidgetConfigurationInterface, WidgetInterface};
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ViewFactoryHelper;

/**
 * AbstractWidget.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
abstract class AbstractWidget implements WidgetInterface, AdditionalCssInterface, JavaScriptInterface
{
    protected ServerRequestInterface $request;

    /**
     * @param array<string, mixed> $buttons
     * @param array<string, mixed> $options
     */
    public function __construct(
        protected readonly WidgetConfigurationInterface $configuration,
        protected readonly ListDataProviderInterface $dataProvider,
        protected readonly ?ButtonProviderInterface $buttonProvider = null,
        protected readonly array $buttons = [],
        protected array $options = [],
    ) {}

    /**
     * @param array<string, mixed> $templateArguments
     */
    public function render(string $templateFile, array $templateArguments): string
    {
        $typo3Version = GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion();
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addInlineLanguageLabelFile('EXT:ximatypo3contentplanner/Resources/Private/Language/locallang.xlf');

        return ViewFactoryHelper::renderView($templateFile, array_merge($templateArguments, [
            'showTitle' => ($typo3Version <= 12),
            'filterBgFix' => ($typo3Version <= 12),
        ]));
    }

    abstract public function renderWidgetContent(): string;

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return string[]
     */
    public function getCssFiles(): array
    {
        return ['EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Widgets.css', 'EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Comments.css'];
    }

    /**
     * @return list<JavaScriptModuleInstruction>
     */
    public function getJavaScriptModuleInstructions(): array
    {
        return [
            JavaScriptModuleInstruction::create('@xima/ximatypo3contentplanner/filter-status.js'),
            JavaScriptModuleInstruction::create('@xima/ximatypo3contentplanner/comments-list-modal.js'),
        ];
    }
}
