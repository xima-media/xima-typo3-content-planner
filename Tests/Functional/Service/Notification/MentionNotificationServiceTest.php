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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Service\Notification;

use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{WatchMode};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\WatcherRepository;
use Xima\XimaTypo3ContentPlanner\Service\Notification\MentionNotificationService;
use Xima\XimaTypo3ContentPlanner\Service\WatcherService;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * MentionNotificationServiceTest.
 *
 * Full-stack proof of the "mute-vs-mention" decision (issue #305), against a real database and
 * the real, DI-tagged {@see \Xima\XimaTypo3ContentPlanner\Service\Notification\Channel\DatabaseChannel}:
 * a mention delivers its notification even to a user who has sticky-muted the record
 * ({@see WatchMode::ManualUnwatch}), but does not re-subscribe them.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class MentionNotificationServiceTest extends AbstractFunctionalTestCase
{
    private const MENTION_CONTENT = '<p>Hey <a class="ctp-mention" data-mention-uid="10">@Member</a>!</p>';

    private MentionNotificationService $subject;
    private WatcherService $watcherService;
    private WatcherRepository $watcherRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/comments.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/be_groups_mention.csv');
        $this->loginBackendUser();
        $this->enableExtensionFeature(Configuration::FEATURE_NOTIFICATION_CHANNEL_DATABASE, '1');

        $this->subject = $this->get(MentionNotificationService::class);
        $this->watcherService = $this->get(WatcherService::class);
        $this->watcherRepository = $this->get(WatcherRepository::class);
    }

    #[Test]
    public function notifiesAndAutoWatchesAMentionedUserWhoWasNotPreviouslyWatching(): void
    {
        $this->subject->notifyMentions('pages', 1, self::MENTION_CONTENT, 1, 1);

        $rows = $this->fetchAllNotifications();
        self::assertCount(1, $rows);
        self::assertSame(10, (int) $rows[0]['backend_user']);
        self::assertSame('mentioned', $rows[0]['event_type']);
        self::assertSame('mentioned', $rows[0]['reason']);

        self::assertSame(WatchMode::Auto, $this->watcherRepository->findMode('pages', 1, 10));
    }

    #[Test]
    public function deliversTheNotificationToAMutedUserButDoesNotReSubscribeThem(): void
    {
        // uid 10 has explicitly muted this record - the sticky manual_unwatch state.
        $this->watcherService->unwatch('pages', 1, 10);
        self::assertSame(WatchMode::ManualUnwatch, $this->watcherRepository->findMode('pages', 1, 10));

        $this->subject->notifyMentions('pages', 1, self::MENTION_CONTENT, 1, 1);

        // The mention still reaches them...
        $rows = $this->fetchAllNotifications();
        self::assertCount(1, $rows);
        self::assertSame(10, (int) $rows[0]['backend_user']);
        self::assertSame('mentioned', $rows[0]['event_type']);

        // ...but they remain muted for anything else - the mention did not re-watch them.
        self::assertSame(WatchMode::ManualUnwatch, $this->watcherRepository->findMode('pages', 1, 10));
        self::assertFalse($this->watcherService->isWatching('pages', 1, 10));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllNotifications(): array
    {
        return $this->getConnectionPool()
            ->getConnectionForTable(Configuration::TABLE_NOTIFICATION)
            ->select(['*'], Configuration::TABLE_NOTIFICATION, [], [], ['backend_user' => 'ASC'])
            ->fetchAllAssociative();
    }
}
