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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Controller\NotificationController;
use Xima\XimaTypo3ContentPlanner\Domain\Model\{Notification, NotificationEventType, NotificationReason};
use Xima\XimaTypo3ContentPlanner\Domain\Repository\NotificationRepository;
use Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationCenterDataProvider;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * NotificationControllerTest.
 *
 * The AJAX backend for the toolbar notification center (issue #301). Covers the "mark as read
 * (single/all) updates read_at and badge" and "no leaking titles of inaccessible records"
 * acceptance criteria end to end, and that {@see NotificationRepository::markAsRead()}'s
 * recipient scoping is actually reachable from the controller: one user can't mark another
 * user's notification read via a guessed uid.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class NotificationControllerTest extends AbstractFunctionalTestCase
{
    private NotificationRepository $notificationRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $this->notificationRepository = $this->get(NotificationRepository::class);
    }

    #[Test]
    public function listActionReturnsRenderedFragmentAndCounts(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        $this->createNotification(recipientUid: 1);
        $this->createNotification(recipientUid: 1);
        $this->createNotification(recipientUid: 2);

        $response = $this->createController()->listAction($this->createRequest([]));
        $payload = json_decode((string) $response->getBody(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(2, $payload['unreadCount']);
        self::assertSame('2', $payload['badgeLabel']);
        self::assertStringContainsString('content-planner-notification-entry', $payload['result']);
    }

    #[Test]
    public function listActionDeniesUserWithoutContentPlannerAccess(): void
    {
        // Editor (uid 2) is a non-admin without any content planner permission.
        $this->loginBackendUser(2);

        $response = $this->createController()->listAction($this->createRequest([]));

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function listActionDoesNotLeakTheTitleOfAnInaccessibleRecord(): void
    {
        $this->loginBackendUser(1);
        $this->setUpBackendRequest();
        // Page uid 4 has a pid pointing at a non-existent parent page, so
        // BackendUtility::readPageAccess() cannot resolve it - even for an admin.
        $this->createNotification(recipientUid: 1, table: 'pages', recordUid: 4, title: 'Confidential board minutes');

        $response = $this->createController()->listAction($this->createRequest([]));
        $payload = json_decode((string) $response->getBody(), true);

        self::assertStringNotContainsString('Confidential board minutes', $payload['result']);
    }

    #[Test]
    public function markReadActionMarksTheOwnNotificationAndReturnsTheUpdatedBadge(): void
    {
        $this->loginBackendUser(1);
        $this->createNotification(recipientUid: 1);
        $uid = $this->fetchLatestNotificationUid();

        $response = $this->createController()->markReadAction($this->createRequest(['uid' => $uid]));
        $payload = json_decode((string) $response->getBody(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $payload['unreadCount']);
        self::assertNotNull($this->fetchReadAt($uid));
    }

    #[Test]
    public function markReadActionCannotMarkAnotherUsersNotificationAsRead(): void
    {
        $this->createNotification(recipientUid: 2);
        $uid = $this->fetchLatestNotificationUid();

        $this->loginBackendUser(1);
        $this->createController()->markReadAction($this->createRequest(['uid' => $uid]));

        self::assertNull($this->fetchReadAt($uid));
    }

    #[Test]
    public function markReadActionReturnsBadRequestWhenUidIsMissing(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->markReadAction($this->createRequest([]));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function markAllReadActionMarksEveryUnreadNotificationOfTheCurrentUser(): void
    {
        $this->loginBackendUser(1);
        $this->createNotification(recipientUid: 1);
        $this->createNotification(recipientUid: 1);
        $this->createNotification(recipientUid: 2);

        $response = $this->createController()->markAllReadAction($this->createRequest([]));
        $payload = json_decode((string) $response->getBody(), true);

        self::assertSame(0, $payload['unreadCount']);
        self::assertSame(0, $this->get(NotificationCenterDataProvider::class)->getUnreadCount(1));
        self::assertSame(1, $this->get(NotificationCenterDataProvider::class)->getUnreadCount(2));
    }

    private function createNotification(int $recipientUid, string $table = 'pages', int $recordUid = 1, string $title = 'Home'): void
    {
        $this->notificationRepository->create(new Notification(
            $recipientUid,
            NotificationEventType::StatusChanged,
            $table,
            $recordUid,
            null,
            NotificationReason::WatchingManually,
            ['version' => 1, 'title' => $title],
            time(),
        ));
    }

    private function fetchLatestNotificationUid(): int
    {
        $row = $this->getConnectionPool()
            ->getConnectionForTable(Configuration::TABLE_NOTIFICATION)
            ->select(['uid'], Configuration::TABLE_NOTIFICATION, [], [], ['uid' => 'DESC'])
            ->fetchAssociative();

        return (int) $row['uid'];
    }

    private function fetchReadAt(int $uid): ?int
    {
        $row = $this->getConnectionPool()
            ->getConnectionForTable(Configuration::TABLE_NOTIFICATION)
            ->select(['read_at'], Configuration::TABLE_NOTIFICATION, ['uid' => $uid])
            ->fetchAssociative();

        return null === $row['read_at'] ? null : (int) $row['read_at'];
    }

    private function createController(): NotificationController
    {
        return new NotificationController(
            $this->get(NotificationCenterDataProvider::class),
            $this->notificationRepository,
        );
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function createRequest(array $queryParams): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn($queryParams);

        return $request;
    }
}
