<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Widgets;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Dashboard\Widgets\{ButtonProviderInterface, ListDataProviderInterface, WidgetConfigurationInterface};
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\RecordPreset;

/**
 * ContentKpiWidget.
 *
 * Three figures (assigned to me, open to-dos, open comments) that each link to the records
 * module with the matching preset applied, so a figure always equals the list behind it.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ContentKpiWidget extends AbstractWidget
{
    private const TILES = [
        'assignee' => ['icon' => 'content-planner-user-circle', 'label' => 'widgets.contentPlanner.kpi.assignee'],
        'todo' => ['icon' => 'content-planner-checkbox', 'label' => 'filter.openTodos'],
        'openComments' => ['icon' => 'content-planner-message-circle', 'label' => 'filter.openComments'],
    ];

    /**
     * @param array<string, mixed> $buttons
     * @param array<string, mixed> $options
     */
    public function __construct(
        WidgetConfigurationInterface $configuration,
        ListDataProviderInterface $dataProvider,
        private readonly UriBuilder $uriBuilder,
        ?ButtonProviderInterface $buttonProvider = null,
        array $buttons = [],
        array $options = [],
    ) {
        parent::__construct($configuration, $dataProvider, $buttonProvider, $buttons, $options);
    }

    public function renderWidgetContent(): string
    {
        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];
        $items = $this->dataProvider->getItems();
        $tiles = [];
        foreach (RecordPreset::cases() as $preset) {
            $item = $items[$preset->value];
            $tiles[] = [
                'link' => $item['count'] > 0 ? (string) $this->uriBuilder->buildUriFromRoute('content_planner_records', [$preset->value => $preset->filterValue($backendUser->getUserId())]) : '',
                'icon' => self::TILES[$preset->value]['icon'],
                'label' => $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:'.self::TILES[$preset->value]['label']),
                'figure' => $item['countLabel'],
            ];
        }

        return $this->render('Backend/Widgets/ContentKpiList.html', ['tiles' => $tiles]);
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
