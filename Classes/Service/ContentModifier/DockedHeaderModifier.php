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
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Service\Header\{HeaderMode, InfoGenerator};
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\RouteUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

/**
 * DockedHeaderModifier.
 *
 * The "docked" headerDisplayMode counterpart to DrawBackendHeaderListener, which injects the
 * status header above the page content instead - a placement that listener switches off for
 * "docked" mode (see its early return there). This modifier splices a compact version of the
 * same header into the doc header's button row instead, anchored on the button row's class
 * list containing both "docheader" and "buttons": the only overlap between TYPO3 v13's
 * `module-docheader-bar module-docheader-bar-buttons` (nested inside a single outer
 * `.module-docheader`) and v14's `module-docheader module-docheader-buttons` (the button row
 * itself also carries `.module-docheader`, see the "v13/v14 doc header markup differs" project
 * note) - order and exact class names differ, but that pair of substrings does not.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class DockedHeaderModifier extends AbstractModifier implements ModifierInterface
{
    public function __construct(
        StatusRepository $statusRepository,
        RecordRepository $recordRepository,
        private readonly InfoGenerator $infoGenerator,
    ) {
        parent::__construct($statusRepository, $recordRepository);
    }

    public function isRelevant(ServerRequestInterface $request): bool
    {
        return SystemEnvironmentBuilder::REQUESTTYPE_BE === $request->getAttribute('applicationType')
            && ExtensionUtility::isDockedDisplayModeEnabled()
            && PermissionUtility::checkContentStatusVisibility()
            && null !== $request->getAttribute('module')
            && RouteUtility::isPageLayoutRoute($request->getAttribute('module')->getIdentifier());
    }

    public function modify(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $content = $response->getBody()->__toString();

        $pid = (int) ($request->getQueryParams()['id'] ?? 0);
        if (0 === $pid) {
            return $response;
        }

        $header = $this->infoGenerator->generateStatusHeader(HeaderMode::WEB_LAYOUT, null, 'pages', $pid, true);
        if (false === $header) {
            return $response;
        }

        return $this->replaceBody($response, $this->insertBeforeButtonBar($content, $header));
    }

    private function insertBeforeButtonBar(string $content, string $header): string
    {
        $newContent = preg_replace(
            '/<div\s+class="(?=[^"]*docheader)(?=[^"]*buttons)[^"]*"[^>]*>/i',
            $header.'$0',
            $content,
            1,
        );

        return $newContent ?? $content;
    }
}
