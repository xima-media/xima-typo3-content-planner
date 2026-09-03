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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Service\Notification;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Service\Notification\ContentChangePayloadMerger;

/**
 * ContentChangePayloadMergerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ContentChangePayloadMergerTest extends TestCase
{
    #[Test]
    public function mergeSumsChangeCounts(): void
    {
        $existing = ['version' => 1, 'title' => 'Home', 'changeCount' => 5, 'actorUids' => [1]];
        $incoming = ['version' => 1, 'title' => 'Home', 'changeCount' => 1, 'actorUids' => [1]];

        $merged = ContentChangePayloadMerger::merge($existing, $incoming);

        self::assertSame(6, $merged['changeCount']);
    }

    #[Test]
    public function mergeUnionsActorUidsWithoutDuplicates(): void
    {
        $existing = ['version' => 1, 'title' => 'Home', 'changeCount' => 3, 'actorUids' => [1, 2]];
        $incoming = ['version' => 1, 'title' => 'Home', 'changeCount' => 1, 'actorUids' => [2]];

        $merged = ContentChangePayloadMerger::merge($existing, $incoming);

        self::assertSame([1, 2], $merged['actorUids']);
    }

    #[Test]
    public function mergeAddsANewActorToTheSet(): void
    {
        $existing = ['version' => 1, 'title' => 'Home', 'changeCount' => 3, 'actorUids' => [1]];
        $incoming = ['version' => 1, 'title' => 'Home', 'changeCount' => 1, 'actorUids' => [2]];

        $merged = ContentChangePayloadMerger::merge($existing, $incoming);

        self::assertSame([1, 2], $merged['actorUids']);
    }

    #[Test]
    public function mergeTakesTheTitleFromTheIncomingPayload(): void
    {
        $existing = ['version' => 1, 'title' => 'Old title', 'changeCount' => 1, 'actorUids' => []];
        $incoming = ['version' => 1, 'title' => 'Renamed', 'changeCount' => 1, 'actorUids' => []];

        $merged = ContentChangePayloadMerger::merge($existing, $incoming);

        self::assertSame('Renamed', $merged['title']);
    }

    #[Test]
    public function mergeTreatsAMissingChangeCountAsZero(): void
    {
        $merged = ContentChangePayloadMerger::merge([], ['changeCount' => 1, 'actorUids' => [3]]);

        self::assertSame(1, $merged['changeCount']);
        self::assertSame([3], $merged['actorUids']);
    }

    #[Test]
    public function fourteenChangesByTwoActorsCollapseToOneMergedPayloadWithTheRightCounters(): void
    {
        $payload = [];
        $actors = [1, 2];
        for ($i = 0; $i < 14; ++$i) {
            $actorUid = $actors[$i % 2];
            $payload = ContentChangePayloadMerger::merge($payload, [
                'version' => 1,
                'title' => 'Home',
                'changeCount' => 1,
                'actorUids' => [$actorUid],
            ]);
        }

        self::assertSame(14, $payload['changeCount']);
        self::assertSame([1, 2], $payload['actorUids']);
    }
}
