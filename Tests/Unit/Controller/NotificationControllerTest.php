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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Xima\XimaTypo3ContentPlanner\Controller\NotificationController;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\NotificationRepository;
use Xima\XimaTypo3ContentPlanner\Service\Notification\NotificationCenterDataProvider;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

/**
 * NotificationControllerTest.
 *
 * Covers the "no authenticated backend user" guard shared by every action. The
 * content-status-visibility gate and record-permission-checked rendering both need a real
 * database and a genuine BackendUserAuthentication, and are covered functionally, see
 * {@see \Xima\XimaTypo3ContentPlanner\Tests\Functional\Controller\NotificationControllerTest}.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class NotificationControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
        PermissionUtility::resetCache();
    }

    #[Test]
    public function notificationAjaxRoutesAreNotPublic(): void
    {
        $routes = require __DIR__.'/../../../Configuration/Backend/AjaxRoutes.php';

        foreach (['ximatypo3contentplanner_notifications', 'ximatypo3contentplanner_notifications_read', 'ximatypo3contentplanner_notifications_read_all'] as $routeIdentifier) {
            self::assertArrayHasKey($routeIdentifier, $routes);
            self::assertNotSame(
                'public',
                $routes[$routeIdentifier]['access'] ?? null,
                "Route \"$routeIdentifier\" must not be publicly accessible (auth + CSRF token required).",
            );
        }
    }

    #[Test]
    public function listActionDeniesAccessWhenNoBackendUserIsAuthenticated(): void
    {
        unset($GLOBALS['BE_USER']);

        $response = $this->createController()->listAction($this->createRequest([]));

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function markReadActionDeniesAccessWhenNoBackendUserIsAuthenticated(): void
    {
        unset($GLOBALS['BE_USER']);

        $response = $this->createController()->markReadAction($this->createRequest(['uid' => 1]));

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function markAllReadActionDeniesAccessWhenNoBackendUserIsAuthenticated(): void
    {
        unset($GLOBALS['BE_USER']);

        $response = $this->createController()->markAllReadAction($this->createRequest([]));

        self::assertSame(403, $response->getStatusCode());
    }

    private function createController(): NotificationController
    {
        return new NotificationController(
            $this->createMock(NotificationCenterDataProvider::class),
            $this->createMock(NotificationRepository::class),
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
