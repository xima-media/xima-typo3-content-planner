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

namespace Xima\XimaTypo3ContentPlanner\Utility\Security;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * WorkspaceUtility.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class WorkspaceUtility
{
    /**
     * Whether the backend user works in a workspace. A status applies to the live record
     * regardless, but the form and the modal say so.
     */
    public static function isActive(): bool
    {
        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];

        return $backendUser->workspace > 0;
    }
}
