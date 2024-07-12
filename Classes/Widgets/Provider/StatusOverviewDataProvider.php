<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets\Provider;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Dashboard\Widgets\ChartDataProviderInterface;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;

class StatusOverviewDataProvider implements ChartDataProviderInterface
{
    public function __construct(protected StatusRepository $statusRepository)
    {
    }

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

    public function countPageStatus(int $status = null): int
    {
        return count(ContentUtility::getRecordsByFilter(null, $status));
    }

    protected function calculateStatusCounts(): void
    {
        foreach ($this->statusRepository->findAll() as $status) {
            $this->labels[] = $status->getTitle();
            $this->data[] = $this->countPageStatus($status->getUid());
            $this->colors[] = Configuration::STATUS_COLOR_CODES[$status->getColor()];
        }
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
