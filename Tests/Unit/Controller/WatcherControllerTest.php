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
use Xima\XimaTypo3ContentPlanner\Controller\WatcherController;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Service\{WatcherPresentationService, WatcherService};
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

/**
 * WatcherControllerTest.
 *
 * Covers the "no authenticated backend user" guard and the missing-parameters guard, which need
 * no database. The content-status-visibility gate, record-permission checks and the toggle
 * round-trip itself all need a real database and a genuine BackendUserAuthentication, and are
 * covered functionally, see
 * {@see \Xima\XimaTypo3ContentPlanner\Tests\Functional\Controller\WatcherControllerTest}.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class WatcherControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
        PermissionUtility::resetCache();
    }

    #[Test]
    public function watcherAjaxRouteIsNotPublic(): void
    {
        $routes = require __DIR__.'/../../../Configuration/Backend/AjaxRoutes.php';

        self::assertArrayHasKey('ximatypo3contentplanner_watch_toggle', $routes);
        self::assertNotSame(
            'public',
            $routes['ximatypo3contentplanner_watch_toggle']['access'] ?? null,
            'Route "ximatypo3contentplanner_watch_toggle" must not be publicly accessible (auth + CSRF token required).',
        );
    }

    #[Test]
    public function toggleActionReturnsBadRequestWhenParametersMissing(): void
    {
        $response = $this->createController()->toggleAction($this->createRequest([]));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function toggleActionDeniesAccessWhenNoBackendUserIsAuthenticated(): void
    {
        unset($GLOBALS['BE_USER']);

        $response = $this->createController()->toggleAction(
            $this->createRequest(['table' => 'pages', 'uid' => 1]),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    private function createController(): WatcherController
    {
        return new WatcherController(
            $this->createMock(RecordRepository::class),
            $this->createMock(WatcherService::class),
            $this->createMock(WatcherPresentationService::class),
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
