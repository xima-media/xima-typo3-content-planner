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
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Service\Header\{HeaderMode, InfoGenerator};
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\RouteUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function in_array;
use function strpos;
use function substr;

/**
 * ContentElementHeaderModifier.
 *
 * The "chip" headerDisplayMode counterpart to {@see WebLayoutModifier} (which only runs in
 * "banner" mode): decorates every content element in the page module with a compact version
 * of the page status header (icon, status title, assignee/comment/watch actions, "..."
 * edit-status menu) placed *above* core's own `.t3-page-ce-header` title row.
 *
 * That row is out of reach for {@see \TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent}
 * (it only controls the preview body below the title row), so - like WebLayoutModifier and
 * RecordEditModifier already do for "banner" mode - this splices real markup into the raw
 * page module HTML instead, anchored on each content element's unique
 * `id="element-tt_content-{uid}"` (stable across TYPO3 v13/v14, see
 * typo3/cms-backend Resources/Private/Partials/PageLayout/Record.html). This is a deliberate
 * trade-off: coupling to core markup that is not a public API, in exchange for a header that
 * sits where the page-level one does, above the whole content element instead of inside it.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ContentElementHeaderModifier extends AbstractModifier implements ModifierInterface
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
            && !ExtensionUtility::isBannerDisplayModeEnabled()
            && PermissionUtility::checkContentStatusVisibility()
            && null !== $request->getAttribute('module')
            && RouteUtility::isPageLayoutRoute($request->getAttribute('module')->getIdentifier())
            && in_array('tt_content', $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'], true);
    }

    public function modify(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $content = $response->getBody()->__toString();

        $pid = (int) ($request->getQueryParams()['id'] ?? 0);
        if (0 === $pid) {
            return $response;
        }

        return $this->replaceBody($response, $this->injectContentElementHeaders($content, $pid));
    }

    private function injectContentElementHeaders(string $content, int $pid): string
    {
        foreach ($this->recordRepository->findByPid('tt_content', $pid) as $record) {
            $header = $this->infoGenerator->generateStatusHeader(HeaderMode::CONTENT_ELEMENT, $record, 'tt_content');
            if (false === $header) {
                continue;
            }

            $content = $this->insertBeforeHeaderRow($content, (int) $record['uid'], $header);
        }

        return $content;
    }

    /**
     * Anchored on the content element's own unique `id`, so an unrelated content element
     * sharing e.g. the same status colour is never matched instead.
     */
    private function insertBeforeHeaderRow(string $content, int $uid, string $header): string
    {
        $anchorPosition = strpos($content, 'id="element-tt_content-'.$uid.'"');
        if (false === $anchorPosition) {
            return $content;
        }

        $headerRowPosition = strpos($content, '<div class="t3-page-ce-header', $anchorPosition);
        if (false === $headerRowPosition) {
            return $content;
        }

        return substr($content, 0, $headerRowPosition).$header.substr($content, $headerRowPosition);
    }
}
