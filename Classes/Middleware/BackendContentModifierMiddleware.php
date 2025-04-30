<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Service\ContentModifier\FileList;
use Xima\XimaTypo3ContentPlanner\Service\ContentModifier\RecordEdit;
use Xima\XimaTypo3ContentPlanner\Service\ContentModifier\WebLayout;
use Xima\XimaTypo3ContentPlanner\Service\ContentModifier\WebList;

class BackendContentModifierMiddleware implements MiddlewareInterface
{
    private const MODIFIERS = [
        FileList::class,
        RecordEdit::class,
        WebLayout::class,
        WebList::class,
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        foreach (self::MODIFIERS as $modifier) {
            $modifierInstance = GeneralUtility::makeInstance($modifier);
            if ($modifierInstance->isRelevant($request)) {
                return $modifierInstance->modify($request, $handler);
            }
        }

        return $handler->handle($request);
    }
}
