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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Service\Notification\Channel;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{Notification, NotificationEventType, NotificationReason};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Service\Notification\Channel\ImmediateEmailChannel;
use Xima\XimaTypo3ContentPlanner\Service\Notification\Immediate\ImmediateEmailService;

/**
 * ImmediateEmailChannelTest.
 *
 * Unit coverage for the parts of {@see ImmediateEmailChannel} that don't need a real
 * `ExtensionConfiguration`/database (see
 * {@see \Xima\XimaTypo3ContentPlanner\Tests\Functional\Service\Notification\Channel\ImmediateEmailChannelTest}
 * for `supports()`'s full eligibility truth table).
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ImmediateEmailChannelTest extends TestCase
{
    #[Test]
    public function deliverHandsTheNotificationAndTheFreshlyFetchedRecipientToTheService(): void
    {
        $notification = $this->buildNotification();
        $recipient = [
            'uid' => 2,
            'email' => 'editor@example.com',
            'deleted' => 0,
            'disable' => 0,
            Configuration::FIELD_USER_DIGEST => 1,
            Configuration::FIELD_USER_IMMEDIATE_EMAIL => 1,
        ];

        $backendUserRepository = $this->createMock(BackendUserRepository::class);
        $backendUserRepository->expects(self::once())->method('findByUid')->with(2)->willReturn($recipient);

        $immediateEmailService = $this->createMock(ImmediateEmailService::class);
        $immediateEmailService->expects(self::once())->method('handle')->with($notification, $recipient);

        (new ImmediateEmailChannel($backendUserRepository, $immediateEmailService))->deliver($notification);
    }

    #[Test]
    public function deliverDoesNothingWhenTheRecipientNoLongerExists(): void
    {
        $backendUserRepository = $this->createMock(BackendUserRepository::class);
        $backendUserRepository->method('findByUid')->willReturn(false);

        $immediateEmailService = $this->createMock(ImmediateEmailService::class);
        $immediateEmailService->expects(self::never())->method('handle');

        (new ImmediateEmailChannel($backendUserRepository, $immediateEmailService))->deliver($this->buildNotification());
    }

    private function buildNotification(): Notification
    {
        return new Notification(2, NotificationEventType::StatusChanged, 'pages', 1, 1, NotificationReason::WatchingManually, [], 1000);
    }
}
