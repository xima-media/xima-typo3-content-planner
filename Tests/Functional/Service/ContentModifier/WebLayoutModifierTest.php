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

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Service\ContentModifier;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\{Response, ServerRequest};
use Xima\XimaTypo3ContentPlanner\Service\ContentModifier\WebLayoutModifier;
use Xima\XimaTypo3ContentPlanner\Tests\Functional\AbstractFunctionalTestCase;

/**
 * WebLayoutModifierTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class WebLayoutModifierTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function modifyKeepsTheInnerResponseStatusReasonPhraseAndHeaders(): void
    {
        $this->importSharedDataSet('status.csv');
        $this->importCSVDataSet(__DIR__.'/Fixtures/tt_content.csv');

        $modifier = $this->get(WebLayoutModifier::class);
        $request = (new ServerRequest('https://example.com/typo3/index.php', 'GET'))
            ->withQueryParams(['id' => '1']);

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = new Response('php://temp', 201, ['X-Custom-Header' => ['kept']], 'Created');
                $response->getBody()->write('<html></html>');

                return $response;
            }
        };

        $response = $modifier->modify($request, $handler);
        $body = (string) $response->getBody();

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Created', $response->getReasonPhrase());
        self::assertSame(['kept'], $response->getHeader('X-Custom-Header'));
        self::assertStringContainsString('<html></html>', $body);
        // The status hint for the fixture's tt_content record (pid 1, status 1) proves the
        // body was actually rebuilt, not just passed through unchanged.
        self::assertStringContainsString('data-uid="1"', $body);
    }
}
