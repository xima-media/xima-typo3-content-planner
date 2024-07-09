<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;

class StatusRegistry
{
    public function getStatus(array &$config): void
    {
        $statusRepository = GeneralUtility::makeInstance(StatusRepository::class);

        foreach ($statusRepository->findAll() as $status) {
            $config['items'][] = [
                $status->getTitle(),
                $status->getUid(),
                $status->getColoredIcon(),
            ];
        }
    }

    public function getStatusIcons(array &$config): void
    {
        foreach (Configuration::STATUS_ICONS as $icon) {
            $config['items'][] = [
                $icon,
                $icon,
                "$icon-black",
            ];
        }
    }

    public function getStatusColors(array &$config): void
    {
        foreach (Configuration::STATUS_COLORS as $color) {
            $config['items'][] = [
                $color,
                $color,
                "color-$color",
            ];
        }
    }
}
