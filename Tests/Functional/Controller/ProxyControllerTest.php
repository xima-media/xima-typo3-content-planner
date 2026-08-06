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
 * The redirect validation uses GeneralUtility::sanitizeLocalUrl(), which needs an initialized
 * Environment — only available in the functional bootstrap, hence this case lives here.
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
}
