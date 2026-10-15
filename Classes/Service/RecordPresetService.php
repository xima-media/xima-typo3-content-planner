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

namespace Xima\XimaTypo3ContentPlanner\Service;

use Doctrine\DBAL\Exception;
use Xima\XimaTypo3ContentPlanner\Domain\Model\RecordPreset;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;

use function count;

/**
 * RecordPresetService.
 *
 * Counts the records behind a {@see RecordPreset} through the same query the filtered list
 * uses, so a figure can never disagree with what clicking it shows.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class RecordPresetService
{
    /**
     * Not a page size: a cap large enough to be exact for realistic workloads, while
     * {@see \Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\PaginatedResult::$hasMore} still
     * yields an honest "at least N".
     */
    private const COUNT_LIMIT = 999;

    public function __construct(private readonly RecordRepository $recordRepository) {}

    /**
     * @return array{count: int, countLabel: string}
     *
     * @throws Exception
     */
    public function count(RecordPreset $preset, int $backendUserId): array
    {
        $result = $this->recordRepository->findAllByFilter(
            null,
            null,
            RecordPreset::Assignee === $preset ? $backendUserId : null,
            null,
            RecordPreset::Todo === $preset ? true : null,
            self::COUNT_LIMIT,
            RecordPreset::OpenComments === $preset,
        );
        $count = count($result->items);

        return ['count' => $count, 'countLabel' => $result->hasMore ? $count.'+' : (string) $count];
    }
}
