<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Middleware;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Xima\XimaTypo3ContentPlanner\Service\ContentModifier\{FileListModifier, ModifierInterface, RecordEditModifier, WebLayoutModifier, WebListModifier};

/**
 * BackendContentModifierMiddleware.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class BackendContentModifierMiddleware implements MiddlewareInterface
{
    /** @var ModifierInterface[] */
    private readonly array $modifiers;

    public function __construct(
        FileListModifier $fileListModifier,
        RecordEditModifier $recordEditModifier,
        WebLayoutModifier $webLayoutModifier,
        WebListModifier $webListModifier,
    ) {
        $this->modifiers = [
            $fileListModifier,
            $recordEditModifier,
            $webLayoutModifier,
            $webListModifier,
        ];
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        foreach ($this->modifiers as $modifier) {
            if ($modifier->isRelevant($request)) {
                return $modifier->modify($request, $handler);
            }
        }

        return $handler->handle($request);
    }
}
