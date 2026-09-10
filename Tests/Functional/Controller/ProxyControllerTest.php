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
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use Xima\XimaTypo3ContentPlanner\Controller\ProxyController;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * ProxyControllerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class ProxyControllerTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function messageActionRejectsExternalRedirect(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn([
            'message' => 'status.changed',
            'redirect' => 'https://evil.example/',
        ]);

        $controller = new ProxyController($this->get(FlashMessageService::class));

        self::assertSame(400, $controller->messageAction($request)->getStatusCode());
    }

    #[Test]
    public function messageActionReturnsBadRequestForInvalidMessagePath(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->messageAction(
            $this->createRequest(['message' => 'unknown.path']),
        );

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function messageActionReturnsJsonForValidMessageWithoutRedirect(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->messageAction(
            $this->createRequest(['message' => 'status.changed', 'resultStatus' => 'success']),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertIsString($payload['title']);
        self::assertIsString($payload['message']);
        self::assertArrayHasKey('severity', $payload);
    }

    /**
     * The watch/unwatch toggle's JS module (issue #303) resolves its failure toast through this
     * message path, {@see Xima\XimaTypo3ContentPlanner\Controller\ProxyController::MESSAGES}'s
     * `watch.toggle` entry.
     */
    #[Test]
    public function messageActionResolvesWatchToggleFailureMessage(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->messageAction(
            $this->createRequest(['message' => 'watch.toggle', 'resultStatus' => 'failure']),
        );

        $payload = json_decode((string) $response->getBody(), true);
        self::assertSame(200, $response->getStatusCode());
        self::assertIsString($payload['title']);
        self::assertIsString($payload['message']);
    }

    #[Test]
    public function messageActionReturnsBadRequestForInvalidResultStatus(): void
    {
        $this->loginBackendUser(1);

        $response = $this->createController()->messageAction(
            $this->createRequest(['message' => 'status.changed', 'resultStatus' => 'unknown']),
        );

        self::assertSame(400, $response->getStatusCode());
    }

    // Note: the "valid redirect" success path (sanitized redirect -> flash message
    // queued -> RedirectResponse) and the "valid redirect + invalid message path" 400
    // branch are intentionally not covered here. GeneralUtility::sanitizeLocalUrl()
    // relies on HTTP_HOST/SCRIPT_NAME, which are empty in the CLI functional test
    // environment, so it always sanitizes to '' regardless of input - collapsing every
    // "redirect" case to the same "Invalid redirect target" 400 already covered by
    // messageActionRejectsExternalRedirect(). See docs/memory note on
    // sanitizeLocalUrl being environment dependent.

    private function createController(): ProxyController
    {
        return new ProxyController($this->get(FlashMessageService::class));
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
