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

namespace Xima\XimaTypo3ContentPlanner\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Xima\XimaTypo3ContentPlanner\Middleware\BackendContentModifierMiddleware;
use Xima\XimaTypo3ContentPlanner\Service\ContentModifier\{ContentElementHeaderModifier, DockedHeaderModifier, FileListModifier, FileStorageTreeModifier, RecordEditModifier, WebLayoutModifier, WebListModifier};

/**
 * BackendContentModifierMiddlewareTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class BackendContentModifierMiddlewareTest extends TestCase
{
    #[Test]
    public function processDelegatesToHandlerWhenNoModifierIsRelevant(): void
    {
        $fileStorageTreeModifier = $this->createMock(FileStorageTreeModifier::class);
        $fileListModifier = $this->createMock(FileListModifier::class);
        $recordEditModifier = $this->createMock(RecordEditModifier::class);
        $webLayoutModifier = $this->createMock(WebLayoutModifier::class);
        $webListModifier = $this->createMock(WebListModifier::class);
        $contentElementHeaderModifier = $this->createMock(ContentElementHeaderModifier::class);
        $dockedHeaderModifier = $this->createMock(DockedHeaderModifier::class);

        foreach ([$fileStorageTreeModifier, $fileListModifier, $recordEditModifier, $webLayoutModifier, $webListModifier, $contentElementHeaderModifier, $dockedHeaderModifier] as $modifier) {
            $modifier->expects(self::once())->method('isRelevant')->willReturn(false);
            $modifier->expects(self::never())->method('modify');
        }

        $middleware = new BackendContentModifierMiddleware($fileStorageTreeModifier, $fileListModifier, $recordEditModifier, $webLayoutModifier, $webListModifier, $contentElementHeaderModifier, $dockedHeaderModifier);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        self::assertSame($response, $middleware->process($request, $handler));
    }

    #[Test]
    public function processDelegatesToTheOnlyRelevantModifier(): void
    {
        $fileStorageTreeModifier = $this->createMock(FileStorageTreeModifier::class);
        $fileListModifier = $this->createMock(FileListModifier::class);
        $recordEditModifier = $this->createMock(RecordEditModifier::class);
        $webLayoutModifier = $this->createMock(WebLayoutModifier::class);
        $webListModifier = $this->createMock(WebListModifier::class);
        $contentElementHeaderModifier = $this->createMock(ContentElementHeaderModifier::class);
        $dockedHeaderModifier = $this->createMock(DockedHeaderModifier::class);

        $fileStorageTreeModifier->expects(self::once())->method('isRelevant')->willReturn(false);
        $fileListModifier->expects(self::once())->method('isRelevant')->willReturn(true);
        $recordEditModifier->expects(self::once())->method('isRelevant')->willReturn(false);
        $webLayoutModifier->expects(self::once())->method('isRelevant')->willReturn(false);
        $webListModifier->expects(self::once())->method('isRelevant')->willReturn(false);
        $contentElementHeaderModifier->expects(self::once())->method('isRelevant')->willReturn(false);
        $dockedHeaderModifier->expects(self::once())->method('isRelevant')->willReturn(false);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $fileListModifier->expects(self::once())->method('modify')->with($request, $handler)->willReturn($response);

        $middleware = new BackendContentModifierMiddleware($fileStorageTreeModifier, $fileListModifier, $recordEditModifier, $webLayoutModifier, $webListModifier, $contentElementHeaderModifier, $dockedHeaderModifier);

        self::assertSame($response, $middleware->process($request, $handler));
    }

    #[Test]
    public function processChainsEveryRelevantModifierInOrder(): void
    {
        // The page module in "docked" mode needs both ContentElementHeaderModifier and
        // DockedHeaderModifier to splice markup into the same response - see the class docblock.
        $fileStorageTreeModifier = $this->createMock(FileStorageTreeModifier::class);
        $fileListModifier = $this->createMock(FileListModifier::class);
        $recordEditModifier = $this->createMock(RecordEditModifier::class);
        $webLayoutModifier = $this->createMock(WebLayoutModifier::class);
        $webListModifier = $this->createMock(WebListModifier::class);
        $contentElementHeaderModifier = $this->createMock(ContentElementHeaderModifier::class);
        $dockedHeaderModifier = $this->createMock(DockedHeaderModifier::class);

        foreach ([$fileStorageTreeModifier, $fileListModifier, $recordEditModifier, $webLayoutModifier, $webListModifier] as $modifier) {
            $modifier->expects(self::once())->method('isRelevant')->willReturn(false);
            $modifier->expects(self::never())->method('modify');
        }
        $contentElementHeaderModifier->expects(self::once())->method('isRelevant')->willReturn(true);
        $dockedHeaderModifier->expects(self::once())->method('isRelevant')->willReturn(true);

        $request = $this->createMock(ServerRequestInterface::class);
        $rawResponse = $this->createMock(ResponseInterface::class);
        $decoratedResponse = $this->createMock(ResponseInterface::class);
        $finalResponse = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($rawResponse);

        // ContentElementHeaderModifier is registered before DockedHeaderModifier, so it must
        // be the outer layer: it receives what DockedHeaderModifier (and, inside that, the
        // real handler) already produced, applying its own splice last. Each modifier's own
        // $next->handle() call is exercised for real (not asserted via an argument matcher) to
        // avoid re-entering the very mock invocation being verified.
        $dockedHeaderModifier->expects(self::once())->method('modify')
            ->willReturnCallback(static function (ServerRequestInterface $req, RequestHandlerInterface $next) use ($request, $rawResponse, $decoratedResponse): ResponseInterface {
                self::assertSame($request, $req);
                self::assertSame($rawResponse, $next->handle($request));

                return $decoratedResponse;
            });
        $contentElementHeaderModifier->expects(self::once())->method('modify')
            ->willReturnCallback(static function (ServerRequestInterface $req, RequestHandlerInterface $next) use ($request, $decoratedResponse, $finalResponse): ResponseInterface {
                self::assertSame($request, $req);
                self::assertSame($decoratedResponse, $next->handle($request));

                return $finalResponse;
            });

        $middleware = new BackendContentModifierMiddleware($fileStorageTreeModifier, $fileListModifier, $recordEditModifier, $webLayoutModifier, $webListModifier, $contentElementHeaderModifier, $dockedHeaderModifier);

        self::assertSame($finalResponse, $middleware->process($request, $handler));
    }
}
