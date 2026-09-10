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

use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, NotificationRepository, RecordRepository};
use Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationCenterDataProvider;

/**
 * NotificationCenterDataProviderTest.
 *
 * Covers the toolbar badge's capped display value (issue #301 acceptance criterion "Badge
 * reflects unread count, capped display"). Permission-checked title/link resolution needs a real
 * database (BackendUtility::readPageAccess()) and is covered functionally, see
 * {@see \Xima\XimaTypo3ContentPlanner\Tests\Functional\Service\Notification\NotificationCenterDataProviderTest}.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class NotificationCenterDataProviderTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function unreadCountProvider(): array
    {
        return [
            'zero' => [0, '0'],
            'below cap' => [5, '5'],
            'exactly at cap' => [9, '9'],
            'one above cap' => [10, '9+'],
            'far above cap' => [42, '9+'],
        ];
    }

    #[Test]
    #[DataProvider('unreadCountProvider')]
    public function getUnreadBadgeLabelCapsDisplayAtNinePlus(int $unreadCount, string $expectedLabel): void
    {
        $notificationRepository = $this->createMock(NotificationRepository::class);
        $notificationRepository->method('countUnreadByRecipient')->with(5)->willReturn($unreadCount);

        $subject = new NotificationCenterDataProvider(
            $notificationRepository,
            $this->createMock(RecordRepository::class),
            $this->createMock(BackendUserRepository::class),
        );

        self::assertSame($expectedLabel, $subject->getUnreadBadgeLabel(5));
    }

    #[Test]
    public function getUnreadCountReturnsTheRawRepositoryValueUncapped(): void
    {
        $notificationRepository = $this->createMock(NotificationRepository::class);
        $notificationRepository->method('countUnreadByRecipient')->with(7)->willReturn(42);

        $subject = new NotificationCenterDataProvider(
            $notificationRepository,
            $this->createMock(RecordRepository::class),
            $this->createMock(BackendUserRepository::class),
        );

        self::assertSame(42, $subject->getUnreadCount(7));
    }

    #[Test]
    public function getLatestForDropdownReturnsEmptyListWhenNoNotificationsExist(): void
    {
        $notificationRepository = $this->createMock(NotificationRepository::class);
        $notificationRepository->method('findLatestByRecipient')->with(3, 10)->willReturn([]);

        $subject = new NotificationCenterDataProvider(
            $notificationRepository,
            $this->createMock(RecordRepository::class),
            $this->createMock(BackendUserRepository::class),
        );

        self::assertSame([], $subject->getLatestForDropdown(3));
    }
}
