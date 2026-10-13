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

namespace Xima\XimaTypo3ContentPlanner\Hooks;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\{GeneralUtility, MathUtility};
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function array_key_exists;
use function array_keys;

/**
 * LiveStatusWriter.
 *
 * A status is a state of the planning, not content of a workspace version (docs/design/rules.md,
 * "Dark mode, workspaces, languages"): in a workspace, every status change applies to the live
 * record. The DataHandler would write it into a new workspace version instead, so
 * {@see self::defer()} takes the status out of the datamap before it is processed and
 * {@see self::apply()} writes it to the live record afterwards, through a DataHandler that runs
 * as the same user in the live workspace. Page permissions and the status change itself
 * (history, auto-assignment, notifications) go through the usual DataHandler path.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class LiveStatusWriter
{
    /**
     * @var array<string, array<int, mixed>>
     */
    private array $pending = [];

    public function defer(DataHandler $dataHandler): void
    {
        // The DataHandler's own user, not the global one: the live DataHandler of apply() runs as
        // the same user in the live workspace and must not defer its status again.
        $user = isset($dataHandler->BE_USER) ? $dataHandler->BE_USER : $this->getBackendUser();
        if ($user->workspace <= 0 || !PermissionUtility::canChangeStatus()) {
            return;
        }

        foreach (array_keys($dataHandler->datamap) as $table) {
            if (ExtensionUtility::isRegisteredRecordTable($table)) {
                $this->deferTable($dataHandler, $table);
            }
        }
    }

    public function apply(): void
    {
        if ([] === $this->pending) {
            return;
        }

        $datamap = [];
        foreach ($this->pending as $table => $records) {
            foreach ($records as $uid => $status) {
                $datamap[$table][$uid] = [Configuration::FIELD_STATUS => $status];
            }
        }
        $this->pending = [];

        $liveUser = clone $this->getBackendUser();
        $liveUser->workspace = 0;

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($datamap, [], $liveUser);
        $dataHandler->process_datamap();
    }

    private function deferTable(DataHandler $dataHandler, string $table): void
    {
        foreach ($dataHandler->datamap[$table] as $id => $fields) {
            if (!MathUtility::canBeInterpretedAsInteger($id) || !array_key_exists(Configuration::FIELD_STATUS, $fields)) {
                continue;
            }

            $this->pending[$table][$this->resolveLiveUid($table, (int) $id)] = $fields[Configuration::FIELD_STATUS];
            unset($dataHandler->datamap[$table][$id][Configuration::FIELD_STATUS]);
            if ([] === $dataHandler->datamap[$table][$id]) {
                unset($dataHandler->datamap[$table][$id]);
            }
        }

        if ([] === $dataHandler->datamap[$table]) {
            unset($dataHandler->datamap[$table]);
        }
    }

    /**
     * A form opened on a workspace version submits that version's uid, the status belongs to the
     * live record it versions.
     */
    private function resolveLiveUid(string $table, int $uid): int
    {
        $row = BackendUtility::getRecord($table, $uid, 't3ver_oid');

        return (int) ($row['t3ver_oid'] ?? 0) > 0 ? (int) $row['t3ver_oid'] : $uid;
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];

        return $backendUser;
    }
}
