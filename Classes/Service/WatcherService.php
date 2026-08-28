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
use InvalidArgumentException;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{WatchMode, WatchSource};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\WatcherRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

use function in_array;

/**
 * WatcherService.
 *
 * Public surface for the watcher relation that backs "what matters to me?" for the notification
 * feature. A user is relevant for a record iff they are an active watcher - see the class-level
 * scope description in issue #299 for the full rationale.
 *
 * Kept extraction-friendly for a possible future standalone package: this service depends only on
 * {@see WatcherRepository} (and, transitively, TYPO3's DBAL), never on request/session state.
 *
 * Actor exclusion (a user should not be notified about their own action) is deliberately NOT
 * handled here - watchers include the actor. Exclusion happens at notification dispatch time
 * (see issue #300), which is out of scope for this service.
 *
 * MVP record identity: filelist watching (`sys_file_metadata`, the folder status table) is
 * excluded. Both tables are internally addressed by this extension via a plain (table, uid) pair
 * already, but the record a user actually thinks of as "this file" or "this folder" is identified
 * externally by a combined storage+identifier pair, not by that internal surrogate uid - wiring
 * watch triggers for filelist would need to resolve that identifier first, which is out of scope
 * until the filelist UI trigger (#303) exists. Widening `record_uid` to a string identifier from
 * day one was considered and rejected: it would carry that complexity into every table's watcher
 * rows for no MVP benefit (YAGNI), and can still be introduced later as an additive schema change.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class WatcherService
{
    /**
     * @var string[]
     */
    private const EXCLUDED_TABLES = [
        Configuration::TABLE_FOLDER,
        'sys_file_metadata',
    ];

    public function __construct(private readonly WatcherRepository $watcherRepository) {}

    /**
     * Whether watching is supported for the given table at all: it must be a table this
     * extension actually tracks status for ({@see ExtensionUtility::isRegisteredRecordTable()}),
     * and not one of the explicitly excluded filelist tables. Watching arbitrary tables outside
     * content-planner-enabled ones is out of scope (see issue #299's "Out of scope" section).
     *
     * Auto-watch listeners must check this before calling {@see self::watch()} - their triggering
     * events can legitimately fire for excluded tables too (e.g. a status change on a folder/file
     * record), and that is not programmer error, unlike calling this service's public methods for
     * an unwatchable table directly, which throws.
     */
    public function isWatchable(string $table): bool
    {
        return ExtensionUtility::isRegisteredRecordTable($table) && !in_array($table, self::EXCLUDED_TABLES, true);
    }

    /**
     * @throws Exception
     */
    public function watch(string $table, int $uid, int $beUser, WatchSource $source): void
    {
        $this->assertWatchableTable($table);
        $uid = $this->watcherRepository->normalizeToDefaultLanguageUid($table, $uid);

        $isExplicitAction = WatchSource::Manual === $source;

        if (!$isExplicitAction) {
            $existingMode = $this->watcherRepository->findMode($table, $uid, $beUser);

            // Sticky: an auto-watch trigger never touches an existing explicit (manual) decision,
            // whether the user is already manually watching or has manually unwatched.
            if (WatchMode::ManualWatch === $existingMode || WatchMode::ManualUnwatch === $existingMode) {
                return;
            }
        }

        $mode = $isExplicitAction ? WatchMode::ManualWatch : WatchMode::Auto;
        $this->watcherRepository->upsert($table, $uid, $beUser, $mode, $source);
    }

    /**
     * Sticky unwatch: always wins, regardless of the prior mode, and only an explicit
     * {@see self::watch()} call with {@see WatchSource::Manual} reactivates it.
     *
     * @throws Exception
     */
    public function unwatch(string $table, int $uid, int $beUser): void
    {
        $this->assertWatchableTable($table);
        $uid = $this->watcherRepository->normalizeToDefaultLanguageUid($table, $uid);

        $this->watcherRepository->upsert($table, $uid, $beUser, WatchMode::ManualUnwatch, WatchSource::Manual);
    }

    /**
     * @return array<int, int> backend user UIDs, excluding manual_unwatch
     *
     * @throws Exception
     */
    public function getActiveWatchers(string $table, int $uid): array
    {
        $this->assertWatchableTable($table);
        $uid = $this->watcherRepository->normalizeToDefaultLanguageUid($table, $uid);

        return $this->watcherRepository->findActiveWatcherUserIds($table, $uid);
    }

    /**
     * @throws Exception
     */
    public function isWatching(string $table, int $uid, int $beUser): bool
    {
        $this->assertWatchableTable($table);
        $uid = $this->watcherRepository->normalizeToDefaultLanguageUid($table, $uid);

        $mode = $this->watcherRepository->findMode($table, $uid, $beUser);

        return null !== $mode && WatchMode::ManualUnwatch !== $mode;
    }

    private function assertWatchableTable(string $table): void
    {
        if (!$this->isWatchable($table)) {
            throw new InvalidArgumentException('Table "'.$table.'" does not support watching (not a registered content planner table, or a filelist table excluded in this MVP, see WatcherService class docblock).', 9583021744);
        }
    }
}
