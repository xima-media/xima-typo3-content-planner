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

namespace Xima\XimaTypo3ContentPlanner\Middleware;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Xima\XimaTypo3ContentPlanner\Service\ContentModifier\ModifierInterface;

/**
 * ModifierRequestHandler.
 *
 * Adapts a ModifierInterface into a RequestHandlerInterface so BackendContentModifierMiddleware
 * can chain every modifier relevant to a request instead of only ever running the first one:
 * this handler's handle() calls the modifier's modify() with $next as its handler, so the
 * modifier splices its own markup into whatever response the modifiers further down the chain
 * (closer to the real handler) already produced.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class ModifierRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private ModifierInterface $modifier,
        private RequestHandlerInterface $next,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->modifier->modify($request, $this->next);
    }
}
