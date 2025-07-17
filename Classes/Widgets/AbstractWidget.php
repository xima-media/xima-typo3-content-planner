<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "xima_typo3_content_planner".
 *
 * Copyright (C) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Xima\XimaTypo3ContentPlanner\Widgets;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\AdditionalCssInterface;
use TYPO3\CMS\Dashboard\Widgets\ButtonProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\JavaScriptInterface;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ViewFactoryHelper;

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
        protected array $options = []
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
        return ['EXT:' . Configuration::EXT_KEY . '/Resources/Public/Css/Widgets.css', 'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Css/Comments.css'];
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
