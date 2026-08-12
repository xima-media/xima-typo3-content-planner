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
use Xima\XimaTypo3ContentPlanner\Service\ContentModifier\{FileListModifier, FileStorageTreeModifier, RecordEditModifier, WebLayoutModifier, WebListModifier};

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

        foreach ([$fileStorageTreeModifier, $fileListModifier, $recordEditModifier, $webLayoutModifier, $webListModifier] as $modifier) {
            $modifier->expects(self::once())->method('isRelevant')->willReturn(false);
            $modifier->expects(self::never())->method('modify');
        }

        $middleware = new BackendContentModifierMiddleware($fileStorageTreeModifier, $fileListModifier, $recordEditModifier, $webLayoutModifier, $webListModifier);

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        self::assertSame($response, $middleware->process($request, $handler));
    }

    #[Test]
    public function processDelegatesToFirstRelevantModifierAndStopsCheckingFurtherModifiers(): void
    {
        $fileStorageTreeModifier = $this->createMock(FileStorageTreeModifier::class);
        $fileListModifier = $this->createMock(FileListModifier::class);
        $recordEditModifier = $this->createMock(RecordEditModifier::class);
        $webLayoutModifier = $this->createMock(WebLayoutModifier::class);
        $webListModifier = $this->createMock(WebListModifier::class);

        $fileStorageTreeModifier->expects(self::once())->method('isRelevant')->willReturn(false);
        $fileListModifier->expects(self::once())->method('isRelevant')->willReturn(true);
        $recordEditModifier->expects(self::never())->method('isRelevant');
        $webLayoutModifier->expects(self::never())->method('isRelevant');
        $webListModifier->expects(self::never())->method('isRelevant');

        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $fileListModifier->expects(self::once())->method('modify')->with($request, $handler)->willReturn($response);

        $middleware = new BackendContentModifierMiddleware($fileStorageTreeModifier, $fileListModifier, $recordEditModifier, $webLayoutModifier, $webListModifier);

        self::assertSame($response, $middleware->process($request, $handler));
    }
}
