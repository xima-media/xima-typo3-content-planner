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
use Xima\XimaTypo3ContentPlanner\Domain\Model\WatchMode;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Utility\Data\ContentUtility;

use function array_map;
use function count;
use function implode;

/**
 * WatcherPresentationService.
 *
 * Assembles the read model behind the watch/unwatch toggle UI (issue #303): whether the table
 * supports watching at all, the current viewer's {@see WatchMode} (mapped to one of four visual
 * states - not watching, watching (auto), watching (manual), muted), the total *active*
 * watcher count, and a permission-filtered list of watcher display names for the "show watcher
 * names on hover" requirement.
 *
 * Shared by {@see Header\InfoGenerator} (the initial banner render) and
 * {@see \Xima\XimaTypo3ContentPlanner\Controller\WatcherController} (the AJAX toggle response),
 * so both surfaces derive the same state from a single place.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class WatcherPresentationService
{
    public function __construct(
        private readonly WatcherService $watcherService,
        private readonly BackendUserRepository $backendUserRepository,
    ) {}

    /**
     * `watchable` is `false` for a table this service otherwise renders "inactive"-looking data
     * for (`mode: null`) - the caller (Fluid's `<f:if>`) must check `watchable`, not merely
     * whether this array is non-empty (a non-empty array is always truthy in Fluid, even one
     * whose fields all describe "nothing to show").
     *
     * @return array{watchable: bool, mode: string|null, watching: bool, icon: string, state: string, count: int, watcherNames: list<string>, watcherNamesLabel: string, watchers: list<array{uid: int, name: string}>}
     *
     * @throws Exception
     */
    public function build(string $table, int $uid, int $beUser): array
    {
        if (!$this->watcherService->isWatchable($table)) {
            return [
                'watchable' => false,
                'mode' => null,
                'watching' => false,
                'icon' => WatchMode::iconIdentifier(null),
                'state' => WatchMode::presentationState(null),
                'count' => 0,
                'watcherNames' => [],
                'watcherNamesLabel' => '',
                'watchers' => [],
            ];
        }

        $mode = $this->watcherService->getMode($table, $uid, $beUser);
        $activeWatcherUids = $this->backendUserRepository->filterActiveUids($this->watcherService->getActiveWatchers($table, $uid));
        $watchers = $this->resolveVisibleWatchers($activeWatcherUids);
        $watcherNames = array_map(static fn (array $watcher): string => $watcher['name'], $watchers);

        return [
            'watchable' => true,
            'mode' => $mode?->value,
            'watching' => WatchMode::isWatching($mode),
            'icon' => WatchMode::iconIdentifier($mode),
            'state' => WatchMode::presentationState($mode),
            // Deliberately every active watcher, not just the named ones: a viewer who may
            // not see a colleague still sees that the record is watched. The resulting
            // "3 watchers, 2 names" is intentional and documented; see resolveVisibleWatchers().
            'count' => count($activeWatcherUids),
            'watcherNames' => $watcherNames,
            'watcherNamesLabel' => implode(', ', $watcherNames),
            // Same visibility pool as watcherNames, but keeps the uid alongside each name so
            // the record modal's Watch tab can render an avatar per watcher, not just a label.
            'watchers' => $watchers,
        ];
    }

    /**
     * Watcher names are only ever shown for backend users who are themselves currently
     * content-planner-permitted - the same visibility pool
     * {@see \Xima\XimaTypo3ContentPlanner\Controller\RecordController::assigneeSelectionAction()}
     * already uses for the assignee picker, and
     * {@see \Xima\XimaTypo3ContentPlanner\Controller\MentionController} uses for @-mention
     * suggestions. A user who watched a record in the past but has since lost that permission (or
     * been disabled/deleted) must never leak into this list.
     *
     * Builds the permitted-uid-to-display-name map once (`findAllWithPermission()` already
     * carries `username`/`realName` for every row) instead of one extra query per watcher.
     *
     * @param array<int, int> $watcherUids
     *
     * @return list<array{uid: int, name: string}>
     *
     * @throws Exception
     */
    private function resolveVisibleWatchers(array $watcherUids): array
    {
        if ([] === $watcherUids) {
            return [];
        }

        $namesByUid = [];
        foreach ($this->backendUserRepository->findAllWithPermission() as $user) {
            $namesByUid[(int) $user['uid']] = ContentUtility::generateDisplayName($user);
        }

        $watchers = [];
        foreach ($watcherUids as $watcherUid) {
            $name = $namesByUid[$watcherUid] ?? '';
            if ('' !== $name) {
                $watchers[] = ['uid' => $watcherUid, 'name' => $name];
            }
        }

        return $watchers;
    }
}
