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
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\RouteUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

use function in_array;

/**
 * WebLayoutModifier.
 *
 * CP-25 (#324): only active in the legacy "banner" headerDisplayMode. The default "chip"
 * mode decorates content elements via ContentElementPreviewStatusListener (a
 * PageContentPreviewRenderingEvent listener) instead of this injected `<style>` overlay.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class WebLayoutModifier extends AbstractModifier implements ModifierInterface
{
    public function isRelevant(ServerRequestInterface $request): bool
    {
        return SystemEnvironmentBuilder::REQUESTTYPE_BE === $request->getAttribute('applicationType')
            && ExtensionUtility::isBannerDisplayModeEnabled()
            && null !== $request->getAttribute('module')
            && RouteUtility::isPageLayoutRoute($request->getAttribute('module')->getIdentifier())
            && in_array('tt_content', $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'], true);
    }

    public function modify(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $content = $response->getBody()->__toString();

        $pid = $request->getQueryParams()['id'] ?? 0;
        if (0 === $pid) {
            return $response;
        }

        return $this->replaceBody($response, $content.$this->addStatusHintToContentElement((int) $pid));
    }

    private function addStatusHintToContentElement(int $pid): string
    {
        $styling = [];
        $records = $this->recordRepository->findByPid('tt_content', $pid);

        foreach ($records as $record) {
            $status = $this->statusRepository->findByUid($record[Configuration::FIELD_STATUS]);
            if (!$status instanceof Status) {
                continue;
            }
            $statusColor = Configuration\Colors::get($status->getColor());
            $styling[] = '.t3-page-ce[data-uid="'.$record['uid'].'"] .t3-page-ce-element:before { content: "";display:block;padding:.5em;border-left: 5px solid '.$statusColor.';background-color:'.$statusColor.'; }';
        }

        return '<style>'.implode(' ', $styling).'</style>';
    }
}
