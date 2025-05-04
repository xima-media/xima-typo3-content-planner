<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Service\ContentModifier;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface ModifierInterface
{
    public function modify(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface;

    public function isRelevant(ServerRequestInterface $request): bool;
}
