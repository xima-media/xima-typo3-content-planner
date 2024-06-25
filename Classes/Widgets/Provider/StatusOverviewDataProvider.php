<?php

declare(strict_types=1);

namespace Xima\XimaContentPlanner\Widgets\Provider;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\ChartDataProviderInterface;
use Xima\XimaContentPlanner\Configuration;

class StatusOverviewDataProvider implements ChartDataProviderInterface
{
    protected array $labels = [];
    protected array $data = [];
    protected array $colors = [];

    public function getChartData(): array
    {
        $this->calculateStatusCounts();
        return [
            'labels' => $this->labels,
            'datasets' => [
                [
                    'backgroundColor' => $this->colors,
                    'border' => 0,
                    'data' => $this->data,
                ],
            ],
        ];
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function countPageStatus(string $status = null): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');

        return (int)$queryBuilder
            ->count('*')
            ->from('pages')
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'tx_ximacontentplanner_status',
                    $queryBuilder->createNamedParameter($status, Connection::PARAM_STR)
                ),
            )
            ->executeQuery()
            ->fetchOne();
    }

    protected function calculateStatusCounts(): void
    {
        foreach (Configuration::STATUS_COLORS as $status => $color) {
            $this->labels[] = $this->getLanguageService()->sL('LLL:EXT:xima_content_planner/Resources/Private/Language/locallang_be.xlf:status.' . $status);
            $this->data[] = $this->countPageStatus($status);
            $this->colors[] = $color;
        }
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
