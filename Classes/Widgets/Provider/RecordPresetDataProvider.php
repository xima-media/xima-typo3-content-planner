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

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use Xima\XimaTypo3ContentPlanner\Domain\Model\RecordPreset;
use Xima\XimaTypo3ContentPlanner\Service\RecordPresetService;

/**
 * RecordPresetDataProvider.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class RecordPresetDataProvider implements ListDataProviderInterface
{
    public function __construct(private RecordPresetService $recordPresetService) {}

    /**
     * @return array<string, array{count: int, countLabel: string}>
     *
     * @throws Exception
     */
    public function getItems(): array
    {
        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];
        $backendUserId = $backendUser->getUserId();
        $items = [];
        foreach (RecordPreset::cases() as $preset) {
            $items[$preset->value] = $this->recordPresetService->count($preset, $backendUserId);
        }

        return $items;
    }
}
