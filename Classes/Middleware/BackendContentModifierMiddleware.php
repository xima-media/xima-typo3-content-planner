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
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Xima\XimaTypo3ContentPlanner\Service\ContentModifier\{ContentElementHeaderModifier, DockedHeaderModifier, FileListModifier, FileStorageTreeModifier, ModifierInterface, RecordEditModifier, WebLayoutModifier, WebListModifier};

/**
 * BackendContentModifierMiddleware.
 *
 * Chains every modifier relevant to the request (via ModifierRequestHandler) rather than
 * stopping at the first one: most requests only ever have a single relevant modifier (the
 * existing ones are mutually exclusive by route/headerDisplayMode), but the page module in
 * "docked" mode needs both ContentElementHeaderModifier (unconditional per-content-element
 * headers) and DockedHeaderModifier (the docked bar) to run on the same response.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
readonly class BackendContentModifierMiddleware implements MiddlewareInterface
{
    /** @var ModifierInterface[] */
    private array $modifiers;

    public function __construct(
        FileStorageTreeModifier $fileStorageTreeModifier,
        FileListModifier $fileListModifier,
        RecordEditModifier $recordEditModifier,
        WebLayoutModifier $webLayoutModifier,
        WebListModifier $webListModifier,
        ContentElementHeaderModifier $contentElementHeaderModifier,
        DockedHeaderModifier $dockedHeaderModifier,
    ) {
        $this->modifiers = [
            $fileStorageTreeModifier,
            $fileListModifier,
            $recordEditModifier,
            $webLayoutModifier,
            $webListModifier,
            $contentElementHeaderModifier,
            $dockedHeaderModifier,
        ];
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $relevantModifiers = array_filter(
            $this->modifiers,
            static fn (ModifierInterface $modifier): bool => $modifier->isRelevant($request),
        );

        foreach (array_reverse($relevantModifiers) as $modifier) {
            $handler = new ModifierRequestHandler($modifier, $handler);
        }

        return $handler->handle($request);
    }
}
