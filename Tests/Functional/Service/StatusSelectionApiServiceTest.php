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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\Summary\StatusSelection;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Event\PrepareStatusSelectionEvent;
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;
use Xima\XimaTypo3ContentPlanner\Service\StatusSelectionApiService;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * StatusSelectionApiServiceTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class StatusSelectionApiServiceTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/summary_pages.csv');
        $this->loginBackendUser();
        $this->setUpBackendRequest();
    }

    #[Test]
    public function offersEveryAllowedStatus(): void
    {
        $selection = $this->buildFor('pages', 1);

        self::assertNotNull($selection);
        self::assertCount(3, $selection->items);
        self::assertSame([1, 2, 3], array_map(static fn ($item): int => $item->status->uid, $selection->items));
    }

    #[Test]
    public function flagsTheCurrentStatusAndOffersUnsetting(): void
    {
        // Fixture page 1 carries status 1
        $selection = $this->buildFor('pages', 1);

        self::assertNotNull($selection);
        $current = array_values(array_filter($selection->items, static fn ($item): bool => $item->current));
        self::assertCount(1, $current);
        self::assertSame(1, $current[0]->status->uid);
        self::assertTrue($selection->canUnset);
    }

    #[Test]
    public function doesNotOfferUnsettingForARecordWithoutStatus(): void
    {
        // Fixture page 3 has no status
        $selection = $this->buildFor('pages', 3);

        self::assertNotNull($selection);
        self::assertFalse($selection->canUnset);
        self::assertSame([], array_filter($selection->items, static fn ($item): bool => $item->current));
    }

    #[Test]
    public function aListenerRestrictingTheSelectionRestrictsTheResponseIdentically(): void
    {
        // The acceptance criterion. Backend selection builders key status entries by uid,
        // so a listener unsetting that key must take effect here too.
        $selection = $this->buildFor('pages', 1, static function (array $entries): array {
            unset($entries['2']);

            return $entries;
        });

        self::assertNotNull($selection);
        self::assertSame([1, 3], array_map(static fn ($item): int => $item->status->uid, $selection->items));
    }

    #[Test]
    public function aListenerEmptyingTheSelectionYieldsNoItems(): void
    {
        $selection = $this->buildFor('pages', 1, static fn (array $entries): array => []);

        self::assertNotNull($selection);
        self::assertSame([], $selection->items);
        // Unsetting is governed by permissions, not by the selection listener.
        self::assertTrue($selection->canUnset);
    }

    #[Test]
    public function ignoresEntriesAListenerInvents(): void
    {
        $selection = $this->buildFor('pages', 1, static function (array $entries): array {
            $entries['999'] = 'something the listener added';
            $entries['divider'] = '<hr>';

            return $entries;
        });

        self::assertNotNull($selection);
        self::assertSame([1, 2, 3], array_map(static fn ($item): int => $item->status->uid, $selection->items));
    }

    #[Test]
    public function returnsNullForAnUnregisteredTable(): void
    {
        self::assertNull($this->buildFor('be_users', 1));
    }

    #[Test]
    public function returnsNullForAMissingRecord(): void
    {
        self::assertNull($this->buildFor('pages', 9999));
    }

    #[Test]
    public function serializesWithoutAnyHtmlFragment(): void
    {
        $selection = $this->buildFor('pages', 1);

        self::assertNotNull($selection);
        $payload = (string) json_encode($selection->toArray());
        self::assertStringNotContainsString('<', $payload);
        self::assertStringNotContainsString('&amp;', $payload);
    }

    /**
     * @param (callable(array<string, mixed>): array<string, mixed>)|null $listener
     */
    private function buildFor(string $table, int $uid, ?callable $listener = null): ?StatusSelection
    {
        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->method('dispatch')->willReturnCallback(
            static function (PrepareStatusSelectionEvent $event) use ($listener): PrepareStatusSelectionEvent {
                if (null !== $listener) {
                    $event->setSelection($listener($event->getSelection()));
                }

                return $event;
            },
        );

        $subject = new StatusSelectionApiService(
            $this->get(StatusRepository::class),
            $this->get(RecordRepository::class),
            new StatusSelectionManager($dispatcher),
        );

        return $subject->buildForRecord($table, $uid);
    }
}
