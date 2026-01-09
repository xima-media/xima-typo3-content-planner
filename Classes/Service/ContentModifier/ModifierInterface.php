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

namespace Xima\XimaTypo3ContentPlanner\Service\ContentModifier;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;

/**
 * ModifierInterface.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
interface ModifierInterface
{
    /**
     * Check if this modifier is relevant for the given request.
     */
    public function isRelevant(ServerRequestInterface $request): bool;

    /**
     * Modify the response for the given request.
     */
    public function modify(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface;
}
