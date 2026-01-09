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

namespace Xima\XimaTypo3ContentPlanner\Widgets\Provider;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Dashboard\Widgets\ChartDataProviderInterface;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{RecordRepository, StatusRepository};

use function count;

/**
 * StatusOverviewDataProvider.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class StatusOverviewDataProvider implements ChartDataProviderInterface
{
    /** @var string[] */
    protected array $labels = [];

    /** @var int[] */
    protected array $data = [];

    /** @var string[] */
    protected array $colors = [];

    public function __construct(private readonly StatusRepository $statusRepository, private readonly RecordRepository $recordRepository) {}

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
