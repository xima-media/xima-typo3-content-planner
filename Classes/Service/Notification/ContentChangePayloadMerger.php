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

use function intval;
use function is_array;

/**
 * ContentChangePayloadMerger.
 *
 * Pure payload-merge logic for the content-change notification's aggregation (issue #309):
 * given the payload already stored on today's not-yet-digested `content_changed` notification
 * row and the payload of one more occurrence, produces the merged payload - `changeCount` summed,
 * `actorUids` unioned, `title` refreshed to the latest snapshot. Kept free of any database
 * dependency so the "14 saves by 2 users collapse to one counter" rule
 * ({@see \Xima\XimaTypo3ContentPlanner\Domain\Repository\NotificationRepository::upsertContentChange()})
 * is unit-testable without a database.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentChangePayloadMerger
{
    /**
     * @param array<string, mixed> $existingPayload payload already stored on the aggregated row
     * @param array<string, mixed> $incomingPayload payload of the one additional occurrence
     *
     * @return array<string, mixed>
     */
    public static function merge(array $existingPayload, array $incomingPayload): array
    {
        return [
            ...$existingPayload,
            ...$incomingPayload,
            'changeCount' => self::changeCount($existingPayload) + self::changeCount($incomingPayload),
            'actorUids' => self::mergeActorUids($existingPayload, $incomingPayload),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function changeCount(array $payload): int
    {
        return (int) ($payload['changeCount'] ?? 0);
    }

    /**
     * @param array<string, mixed> $existingPayload
     * @param array<string, mixed> $incomingPayload
     *
     * @return list<int>
     */
    private static function mergeActorUids(array $existingPayload, array $incomingPayload): array
    {
        $merged = [...self::actorUids($existingPayload), ...self::actorUids($incomingPayload)];

        return array_values(array_unique($merged));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<int>
     */
    private static function actorUids(array $payload): array
    {
        $uids = $payload['actorUids'] ?? [];

        return is_array($uids) ? array_map(intval(...), $uids) : [];
    }
}
