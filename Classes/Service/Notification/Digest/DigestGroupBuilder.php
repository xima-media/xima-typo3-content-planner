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

namespace Xima\XimaTypo3ContentPlanner\Service\Notification\Digest;

use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\DigestRecordGroup;
use Xima\XimaTypo3ContentPlanner\Domain\Model\NotificationEventType;

use function count;
use function is_array;

/**
 * DigestGroupBuilder.
 *
 * Pure grouping/dedup logic for the email digest (issue #302): given one recipient's
 * non-digested {@see \Xima\XimaTypo3ContentPlanner\Domain\Repository\NotificationRepository::findPendingByRecipient()}
 * rows (payload already JSON-decoded), collapses every notification for the same record into one
 * {@see DigestRecordGroup} - "5 status changes on 1 record" become one group with a 5-long
 * `$eventCount` and a deduped transition chain, per the issue's acceptance criteria.
 *
 * Deliberately has no database/HTTP dependency of its own: display-label resolution for
 * assignees (a backend user uid needs a lookup to become a name) is delegated to an injected
 * `$assigneeLabelResolver` callback, so this class stays a plain, fast-to-unit-test string/array
 * transformation - see {@see \Xima\XimaTypo3ContentPlanner\Tests\Unit\Service\Notification\Digest\DigestGroupBuilderTest}.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class DigestGroupBuilder
{
    /**
     * @param list<array<string, mixed>> $rows                  one recipient's pending notification rows, each
     *                                                          with a decoded `payload` array under the `payload`
     *                                                          key (not a JSON string)
     * @param callable(mixed): string    $assigneeLabelResolver resolves a raw `previousAssignee`/
     *                                                          `newAssignee` payload value
     *                                                          (a backend user uid, or `null`)
     *                                                          to a display label
     *
     * @return list<DigestRecordGroup>
     */
    public function build(array $rows, callable $assigneeLabelResolver): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['tablename'].':'.$row['record_uid']][] = $row;
        }

        return array_values(array_map(
            fn (array $groupRows): DigestRecordGroup => $this->buildGroup($groupRows, $assigneeLabelResolver),
            $grouped,
        ));
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function buildGroup(array $rows, callable $assigneeLabelResolver): DigestRecordGroup
    {
        usort($rows, static fn (array $a, array $b): int => $a['crdate'] <=> $b['crdate']);
        $latest = $rows[array_key_last($rows)];

        $statusEvents = $this->eventsOfType($rows, NotificationEventType::StatusChanged);
        $assigneeEvents = $this->eventsOfType($rows, NotificationEventType::Assigned);
        $commentEvents = $this->eventsOfType($rows, NotificationEventType::CommentAdded);
        $contentChangeEvents = $this->eventsOfType($rows, NotificationEventType::ContentChanged);

        return new DigestRecordGroup(
            (string) $latest['tablename'],
            (int) $latest['record_uid'],
            (string) ($latest['payload']['title'] ?? ''),
            (string) $latest['reason'],
            $this->buildChain($statusEvents, 'previousStatus', 'newStatus', static fn (mixed $v): string => null === $v ? '' : (string) $v),
            $this->buildChain($assigneeEvents, 'previousAssignee', 'newAssignee', $assigneeLabelResolver),
            [] !== $commentEvents ? (string) ($commentEvents[array_key_last($commentEvents)]['payload']['commentExcerpt'] ?? '') : null,
            count($commentEvents),
            count($rows),
            (int) $latest['crdate'],
            $this->sumContentChangeCounts($contentChangeEvents),
            count($this->unionContentChangeActorUids($contentChangeEvents)),
        );
    }

    /**
     * @param list<array<string, mixed>> $contentChangeEvents
     */
    private function sumContentChangeCounts(array $contentChangeEvents): int
    {
        return array_sum(array_map(
            static fn (array $event): int => (int) ($event['payload']['changeCount'] ?? 0),
            $contentChangeEvents,
        ));
    }

    /**
     * @param list<array<string, mixed>> $contentChangeEvents
     *
     * @return list<int>
     */
    private function unionContentChangeActorUids(array $contentChangeEvents): array
    {
        $actorUids = [];
        foreach ($contentChangeEvents as $event) {
            $eventActorUids = $event['payload']['actorUids'] ?? [];
            if (is_array($eventActorUids)) {
                array_push($actorUids, ...$eventActorUids);
            }
        }

        return array_values(array_unique($actorUids));
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function eventsOfType(array $rows, NotificationEventType $eventType): array
    {
        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => $eventType->value === $row['event_type'],
        ));
    }

    /**
     * Builds a deduped transition chain from a chronological list of "from"/"to" events, e.g.
     * `[null->Draft, Draft->Review, Review->Review, Review->Review, Review->Approved]` collapses
     * to `['Draft', 'Review', 'Approved']`: whether a `null` starting value is worth showing at
     * all is delegated to `$labelResolver` (it returns `''` for "not interesting", e.g. a status
     * that simply had no prior value, or a real label such as "Unassigned" when `null` is itself
     * a meaningful state) - and every subsequent `to` is appended only when it actually differs
     * from the last entry already in the chain. This is what turns any number of no-op/duplicate
     * transitions into a single line, per issue #302's dedup requirement.
     *
     * @param list<array<string, mixed>> $events        chronologically ordered (oldest first)
     * @param callable(mixed): string    $labelResolver
     *
     * @return list<string>
     */
    private function buildChain(array $events, string $fromKey, string $toKey, callable $labelResolver): array
    {
        $chain = [];
        foreach ($events as $event) {
            $to = $labelResolver($event['payload'][$toKey] ?? null);

            if ([] === $chain) {
                $from = $labelResolver($event['payload'][$fromKey] ?? null);
                if ('' !== $from) {
                    $chain[] = $from;
                }
            }

            if ([] === $chain || $chain[array_key_last($chain)] !== $to) {
                $chain[] = $to;
            }
        }

        return $chain;
    }
}
