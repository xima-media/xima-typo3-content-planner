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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\EventListener\Notification;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Event\CommentCreatedEvent;
use Xima\XimaTypo3ContentPlanner\EventListener\Notification\NotifyOnMentionListener;
use Xima\XimaTypo3ContentPlanner\Service\Notification\MentionNotificationService;

/**
 * NotifyOnMentionListenerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class NotifyOnMentionListenerTest extends TestCase
{
    #[Test]
    public function locatesTheCommentContentAndDelegatesToTheMentionNotificationService(): void
    {
        $event = new CommentCreatedEvent('pages', 5, 42, 1);

        $commentRepository = $this->createMock(CommentRepository::class);
        $commentRepository->method('findByUid')->with(42)->willReturn([
            'content' => '<a class="ctp-mention" data-mention-uid="2">@Jane</a>',
        ]);

        $mentionNotificationService = $this->createMock(MentionNotificationService::class);
        $mentionNotificationService->expects(self::once())
            ->method('notifyMentions')
            ->with('pages', 5, '<a class="ctp-mention" data-mention-uid="2">@Jane</a>', 1, 42);

        (new NotifyOnMentionListener($mentionNotificationService, $commentRepository))($event);
    }

    #[Test]
    public function doesNothingWhenTheCommentCannotBeFound(): void
    {
        $event = new CommentCreatedEvent('pages', 5, 42, 1);

        $commentRepository = $this->createMock(CommentRepository::class);
        $commentRepository->method('findByUid')->with(42)->willReturn(false);

        $mentionNotificationService = $this->createMock(MentionNotificationService::class);
        $mentionNotificationService->expects(self::never())->method('notifyMentions');

        (new NotifyOnMentionListener($mentionNotificationService, $commentRepository))($event);
    }

    #[Test]
    public function doesNothingWhenTheCommentHasNoContent(): void
    {
        $event = new CommentCreatedEvent('pages', 5, 42, 1);

        $commentRepository = $this->createMock(CommentRepository::class);
        $commentRepository->method('findByUid')->with(42)->willReturn(['content' => '']);

        $mentionNotificationService = $this->createMock(MentionNotificationService::class);
        $mentionNotificationService->expects(self::never())->method('notifyMentions');

        (new NotifyOnMentionListener($mentionNotificationService, $commentRepository))($event);
    }
}
