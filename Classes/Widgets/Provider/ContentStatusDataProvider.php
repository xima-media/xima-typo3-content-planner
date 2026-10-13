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

use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;

/**
 * ContentStatusDataProvider.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ContentStatusDataProvider implements ListDataProviderInterface
{
    /**
     * @return array<empty, empty>
     */
    public function getItems(): array
    {
        return [];
    }
}
