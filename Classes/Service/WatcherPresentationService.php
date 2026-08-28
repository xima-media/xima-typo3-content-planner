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
     * @return array{watchable: bool, mode: string|null, watching: bool, count: int, watcherNames: list<string>, watcherNamesLabel: string}
     *
     * @throws Exception
     */
    public function build(string $table, int $uid, int $beUser): array
    {
        if (!$this->watcherService->isWatchable($table)) {
            return ['watchable' => false, 'mode' => null, 'watching' => false, 'count' => 0, 'watcherNames' => [], 'watcherNamesLabel' => ''];
        }

        $mode = $this->watcherService->getMode($table, $uid, $beUser);
        $activeWatcherUids = $this->backendUserRepository->activeUids($this->watcherService->getActiveWatchers($table, $uid));
        $watcherNames = $this->resolveVisibleNames($activeWatcherUids);

        return [
            'watchable' => true,
            'mode' => $mode?->value,
            'watching' => WatchMode::isWatching($mode),
            'count' => count($activeWatcherUids),
            'watcherNames' => $watcherNames,
            'watcherNamesLabel' => implode(', ', $watcherNames),
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
     * @return list<string>
     *
     * @throws Exception
     */
    private function resolveVisibleNames(array $watcherUids): array
    {
        if ([] === $watcherUids) {
            return [];
        }

        $namesByUid = [];
        foreach ($this->backendUserRepository->findAllWithPermission() as $user) {
            $namesByUid[(int) $user['uid']] = ContentUtility::generateDisplayName($user);
        }

        $names = [];
        foreach ($watcherUids as $watcherUid) {
            $name = $namesByUid[$watcherUid] ?? '';
            if ('' !== $name) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
