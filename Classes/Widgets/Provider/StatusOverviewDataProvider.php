<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets\Provider;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Dashboard\Widgets\ChartDataProviderInterface;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;

class StatusOverviewDataProvider implements ChartDataProviderInterface
{
    public function __construct(private readonly StatusRepository $statusRepository, private readonly RecordRepository $recordRepository)
    {
    }

    /** @var string[] */
    protected array $labels = [];

    /** @var int[] */
    protected array $data = [];

    /** @var string[] */
    protected array $colors = [];

    /**
    * @return array<string, mixed>
    */
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

    public function countPageStatus(?int $status = null): int
    {
        return count($this->recordRepository->findAllByFilter(status: $status));
    }

    protected function calculateStatusCounts(): void
    {
        foreach ($this->statusRepository->findAll() as $status) {
            $this->labels[] = $status->getTitle();
            $this->data[] = $this->countPageStatus($status->getUid());
            $this->colors[] = Configuration\Colors::get($status->getColor());
        }
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
