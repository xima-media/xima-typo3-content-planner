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

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Widgets\Provider\ContentStatusDataProvider;

use function count;

/**
 * ContentStatusWidget.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ContentStatusWidget extends AbstractWidget
{
    public function renderWidgetContent(): string
    {
        $filter = isset($this->options['useFilter']);

        return $this->render(
            'Backend/Widgets/ContentStatusList.html',
            [
                'configuration' => $this->configuration,
                'options' => $this->options,
                'icon' => 'flag-gray',
                'filter' => $filter ? $this->buildFilterValues() : false,
            ],
        );
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private function buildFilterValues(): array
    {
        /** @var ContentStatusDataProvider $dataProvider */
        $dataProvider = $this->dataProvider;
        $filterValues = [
            'status' => $dataProvider->getStatus(),
            'users' => $dataProvider->getUsers(),
        ];

        $recordTables = ExtensionUtility::getRecordTables();
        if (count($recordTables) > 1) {
            $recordTables = array_map(fn ($table) => ['label' => $this->getLanguageService()->sL($GLOBALS['TCA'][$table]['ctrl']['title']), 'value' => $table], $recordTables);
            $filterValues['types'] = $recordTables;
        }

        return $filterValues;
    }
}
