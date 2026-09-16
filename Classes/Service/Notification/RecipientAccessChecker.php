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

namespace Xima\XimaTypo3ContentPlanner\Service\Notification;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function array_key_exists;
use function is_array;

/**
 * RecipientAccessChecker.
 *
 * Answers "may this backend user still read this record?" for a user who is *not* the one
 * driving the current request.
 *
 * Channels that deliver outside the backend (e-mail, chat) have no logged-in recipient to
 * check against, and {@see \Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility::checkAccessForRecord()}
 * is no help there: it evaluates `$GLOBALS['BE_USER']` and returns true unconditionally for
 * the `_cli_` user a scheduler command runs as. Without this, a notification queued while a
 * user had access would still be delivered after that access was withdrawn - and the payload
 * carries a title snapshot, so the content goes out with it.
 *
 * Resolved users are memoized per instance: a digest run asks about the same recipient once
 * per record, and rebuilding their group data each time would dominate the run.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class RecipientAccessChecker
{
    /** @var array<int, BackendUserAuthentication|null> */
    private array $users = [];

    /**
     * @param array<string, mixed> $record
     */
    public function canAccess(int $backendUserUid, string $table, array $record): bool
    {
        $user = $this->resolveUser($backendUserUid);
        if (!$user instanceof BackendUserAuthentication) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (!$user->check('tables_select', $table)) {
            return false;
        }

        // BackendUtility::readPageAccess() does not evaluate the permission clause alone: it
        // also asks $GLOBALS['BE_USER'] whether the page is inside a web mount. Passing the
        // recipient's clause while the global user is someone else (or, in a scheduler run,
        // null) would therefore check the wrong user or fatal, so the recipient has to be the
        // current user for the duration of the call.
        $previousUser = $GLOBALS['BE_USER'] ?? null;
        $GLOBALS['BE_USER'] = $user;

        try {
            $permissionClause = $user->getPagePermsClause(Permission::PAGE_SHOW);

            // For a page, the page itself is the thing to check. Checking its parent as well
            // would deny every root-level page to every non-admin, since pid 0 is only ever
            // readable by admins.
            if ('pages' === $table) {
                return isset($record['uid'])
                    && false !== BackendUtility::readPageAccess((int) $record['uid'], $permissionClause);
            }

            // Records without a pid (file metadata, folders) carry no page to check against.
            return !isset($record['pid']) || false !== BackendUtility::readPageAccess((int) $record['pid'], $permissionClause);
        } finally {
            $GLOBALS['BE_USER'] = $previousUser;
        }
    }

    /**
     * Null when the account no longer exists or can no longer log in: setBeUserByUid()
     * honours the table's enable columns, so a deleted or disabled user is simply not found.
     */
    private function resolveUser(int $backendUserUid): ?BackendUserAuthentication
    {
        if (array_key_exists($backendUserUid, $this->users)) {
            return $this->users[$backendUserUid];
        }

        $user = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $user->setBeUserByUid($backendUserUid);

        if (!is_array($user->user) || (int) ($user->user['uid'] ?? 0) !== $backendUserUid) {
            return $this->users[$backendUserUid] = null;
        }

        $user->fetchGroupData();

        return $this->users[$backendUserUid] = $user;
    }
}
