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
use TYPO3\CMS\Core\Utility\GeneralUtility;
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

        // The wire shape is unchanged from before the refactor — no key added, none
        // removed — so notification.js and any other consumer see exactly what they did.
        self::assertSame(['title', 'message', 'severity'], array_keys($payload));
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
    public function messageActionServesTheFailureWordingAndSeverity(): void
    {
        $this->loginBackendUser();
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn([
            'message' => 'status.changed',
            'resultStatus' => 'failure',
        ]);

        $payload = json_decode((string) $this->createController()->messageAction($request)->getBody(), true);

        self::assertSame(ContextualFeedbackSeverity::ERROR->value, $payload['severity']);
        self::assertNotEmpty($payload['title']);
    }

    #[Test]
    public function messageActionEnqueuesANotificationAndRedirects(): void
    {
        $this->loginBackendUser();
        $flashMessageService = $this->get(FlashMessageService::class);
        $redirect = $this->localRedirectTarget();
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn([
            'message' => 'status.reset',
            'redirect' => $redirect,
        ]);

        $response = (new ProxyController($flashMessageService, $this->get(ResultMessageService::class)))
            ->messageAction($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($redirect, $response->getHeaderLine('location'));

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

    /**
     * sanitizeLocalUrl() judges a target against TYPO3_SITE_PATH, which under phpunit is
     * derived from the CLI script name. That differs between a local DDEV run and the CI
     * dependency permutations — one accepts only relative targets, another only absolute
     * ones — so no literal is portable. Probing keeps this test about the enqueue and
     * redirect behaviour; rejection of foreign targets is covered separately by
     * messageActionRejectsExternalRedirect().
     */
    private function localRedirectTarget(): string
    {
        foreach (['index.php', 'typo3/index.php', '/typo3/index.php', '/index.php'] as $candidate) {
            if ('' !== GeneralUtility::sanitizeLocalUrl($candidate)) {
                return $candidate;
            }
        }

        self::markTestSkipped('No redirect target passes sanitizeLocalUrl() in this environment.');
    }

    private function createController(): ProxyController
    {
        return new ProxyController(
            $this->get(FlashMessageService::class),
            $this->get(ResultMessageService::class),
        );
    }
}
