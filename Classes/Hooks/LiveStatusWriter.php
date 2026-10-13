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
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\{GeneralUtility, MathUtility};
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function array_filter;
use function array_intersect;
use function array_keys;

/**
 * LiveStatusWriter.
 *
 * A status and an assignee are a state of the planning, not content of a workspace version
 * (docs/design/rules.md, "Dark mode, workspaces, languages"): in a workspace, every change of
 * either applies to the live record, which is also the record the header, the lists and the
 * modal read. The DataHandler would write it into a new workspace version instead, so
 * {@see self::defer()} takes both fields out of the datamap before it is processed and
 * {@see self::apply()} writes them to the live record afterwards, through a DataHandler that runs
 * as the same user in the live workspace. Versions of the record that already exist get the same
 * value, because the page tree and the workspace overlay would show the old one otherwise.
 * Page permissions and the change itself (history, auto-assignment, notifications) go through
 * the usual DataHandler path. The live DataHandler skips the core's workspace checks, so a user
 * who may not edit in the current workspace is refused here, with an error in the DataHandler log.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class LiveStatusWriter
{
    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $pending = [];

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    public function defer(DataHandler $dataHandler): void
    {
        // The DataHandler's own user, not the global one: the live DataHandler of apply() runs as
        // the same user in the live workspace and must not defer again.
        $user = $dataHandler->BE_USER ?? $this->getBackendUser();
        if ($user->workspace <= 0) {
            return;
        }

        $mayEdit = $user->workspaceCheckStageForCurrent(0);
        foreach (array_keys($dataHandler->datamap) as $table) {
            if (ExtensionUtility::isRegisteredRecordTable($table)) {
                $this->deferTable($dataHandler, $table, $mayEdit);
            }
        }
    }

    public function apply(): void
    {
        if ([] === $this->pending) {
            return;
        }

        $datamap = $this->pending;
        $this->pending = [];

        $liveUser = clone $this->getBackendUser();
        $liveUser->workspace = 0;

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($datamap, [], $liveUser);
        $dataHandler->process_datamap();

        foreach ($datamap as $table => $records) {
            foreach ($records as $uid => $fields) {
                $this->syncVersions($table, $uid, $fields);
            }
        }
    }

    private function deferTable(DataHandler $dataHandler, string $table, bool $mayEdit): void
    {
        foreach ($dataHandler->datamap[$table] as $id => $fields) {
            if (!MathUtility::canBeInterpretedAsInteger($id)) {
                continue;
            }

            foreach (array_intersect($this->deferrableFields(), array_keys($fields)) as $field) {
                $this->deferField($dataHandler, $table, (int) $id, $field, $mayEdit);
            }

            if ([] === $dataHandler->datamap[$table][$id]) {
                unset($dataHandler->datamap[$table][$id]);
            }
        }

        if ([] === $dataHandler->datamap[$table]) {
            unset($dataHandler->datamap[$table]);
        }
    }

    private function deferField(DataHandler $dataHandler, string $table, int $id, string $field, bool $mayEdit): void
    {
        if ($mayEdit) {
            $this->pending[$table][$this->resolveLiveUid($table, $id)][$field] = $dataHandler->datamap[$table][$id][$field];
        } else {
            $dataHandler->log($table, $id, 2, null, 1, 'User may not edit in the current workspace, the '.$field.' was not changed.');
        }

        unset($dataHandler->datamap[$table][$id][$field]);
    }

    /**
     * Only the fields the user may change at all, so a change the DataHandler would have refused
     * is not applied live behind its back.
     *
     * @return array<int, string>
     */
    private function deferrableFields(): array
    {
        return array_keys(array_filter([
            Configuration::FIELD_STATUS => PermissionUtility::canChangeStatus(),
            Configuration::FIELD_ASSIGNEE => PermissionUtility::canAssignSelf() || PermissionUtility::canAssignOthers(),
        ]));
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function syncVersions(string $table, int $liveUid, array $fields): void
    {
        if (!isset($GLOBALS['TCA'][$table]['ctrl']['versioningWS'])) {
            return;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $query = $queryBuilder
            ->update($table)
            ->where(
                $queryBuilder->expr()->eq('t3ver_oid', $queryBuilder->createNamedParameter($liveUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->gt('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            );
        foreach ($fields as $field => $value) {
            $query->set($field, $value);
        }
        $query->executeStatement();
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
