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
use TYPO3\CMS\Core\Messaging\{FlashMessageQueue, FlashMessageService};
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use Xima\XimaTypo3ContentPlanner\Controller\ProxyController;
use Xima\XimaTypo3ContentPlanner\Service\ResultMessageService;
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
    public function messageActionReturnsALocalizedEnvelopeWithAnIntegerSeverity(): void
    {
        $this->loginBackendUser();
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn(['message' => 'status.changed']);

        $response = $this->createController()->messageAction($request);
        $payload = json_decode((string) $response->getBody(), true);

        self::assertTrue($payload['success']);
        // notification.js switches on the numeric severity — a serialized enum object
        // would silently fall through to its default branch.
        self::assertSame(ContextualFeedbackSeverity::OK->value, $payload['severity']);
        self::assertIsInt($payload['severity']);
        self::assertNotEmpty($payload['title']);
        self::assertNotEmpty($payload['message']);
        self::assertStringStartsNotWith('LLL:', $payload['title']);
        self::assertStringStartsNotWith('LLL:', $payload['message']);
    }

    #[Test]
    public function messageActionReportsFailureResultsAsUnsuccessful(): void
    {
        $this->loginBackendUser();
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn([
            'message' => 'status.changed',
            'resultStatus' => 'failure',
        ]);

        $payload = json_decode((string) $this->createController()->messageAction($request)->getBody(), true);

        self::assertFalse($payload['success']);
        self::assertSame(ContextualFeedbackSeverity::ERROR->value, $payload['severity']);
    }

    #[Test]
    public function messageActionEnqueuesANotificationAndRedirects(): void
    {
        $this->loginBackendUser();
        $flashMessageService = $this->get(FlashMessageService::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn([
            'message' => 'status.reset',
            // Relative on purpose: under phpunit the CLI script name makes
            // TYPO3_SITE_PATH "vendor/bin/", so sanitizeLocalUrl() rejects the absolute
            // "/typo3/…" paths that production actually passes here.
            'redirect' => 'typo3/module/web/layout',
        ]);

        $response = (new ProxyController($flashMessageService, $this->get(ResultMessageService::class)))
            ->messageAction($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('typo3/module/web/layout', $response->getHeaderLine('location'));

        $messages = $flashMessageService
            ->getMessageQueueByIdentifier(FlashMessageQueue::NOTIFICATION_QUEUE)
            ->getAllMessagesAndFlush();
        self::assertCount(1, $messages);
        self::assertSame(ContextualFeedbackSeverity::NOTICE, $messages[0]->getSeverity());
    }

    #[Test]
    public function messageActionRejectsAnUnknownMessagePath(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn(['message' => 'does.not.exist']);

        self::assertSame(400, $this->createController()->messageAction($request)->getStatusCode());
    }

    #[Test]
    public function messageActionRejectsExternalRedirect(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn([
            'message' => 'status.changed',
            'redirect' => 'https://evil.example/',
        ]);

        self::assertSame(400, $this->createController()->messageAction($request)->getStatusCode());
    }

    private function createController(): ProxyController
    {
        return new ProxyController(
            $this->get(FlashMessageService::class),
            $this->get(ResultMessageService::class),
        );
    }
}
