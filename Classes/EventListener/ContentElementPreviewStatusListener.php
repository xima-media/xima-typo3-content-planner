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

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use TYPO3\CMS\Backend\Preview\StandardPreviewRendererResolver;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\{GridColumn, GridColumnItem};
use TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Utility\{ExtensionUtility, PlannerUtility};
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\ViewUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

/**
 * ContentElementPreviewStatusListener.
 *
 * CP-25 (#324) Level 2: decorates content elements in the page module with a status accent
 * border, a status dot (with tooltip) and a mini comment badge, replacing the injected
 * `<style>` overlay previously added by WebLayoutModifier. Only active in "chip"
 * headerDisplayMode; WebLayoutModifier remains responsible for the equivalent decoration in
 * "banner" mode.
 *
 * This event only lets a listener either leave the default preview content untouched or
 * fully replace it (TYPO3\CMS\Backend\View\Event\PageContentPreviewRenderingEvent is a
 * StoppableEventInterface: setting content stops any later listener, e.g. a CType-specific
 * custom preview renderer, from ever running). To decorate rather than replace, this
 * listener renders the record's own default/CType-specific preview itself - via the same
 * StandardPreviewRendererResolver + GridColumnItem core uses internally
 * (see TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem::getPreview()) - and wraps
 * that content with the accent/dot/badge markup, instead of hijacking it with something
 * different.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
#[AsEventListener(identifier: 'xima-typo3-content-planner/backend/content-element-preview-status')]
final readonly class ContentElementPreviewStatusListener
{
    public function __construct(
        private StatusRepository $statusRepository,
        private CommentRepository $commentRepository,
    ) {}

    public function __invoke(PageContentPreviewRenderingEvent $event): void
    {
        if (!$this->isRelevant($event)) {
            return;
        }

        $record = $event->getRecord();
        $status = $this->statusRepository->findByUid((int) $record[Configuration::FIELD_STATUS]);
        if (!$status instanceof Status) {
            return;
        }

        $event->setPreviewContent($this->decorate($event, $status, $record));
    }

    private function isRelevant(PageContentPreviewRenderingEvent $event): bool
    {
        if (ExtensionUtility::isBannerDisplayModeEnabled()
            || !PermissionUtility::checkContentStatusVisibility()
            || 'tt_content' !== $event->getTable()
            || !ExtensionUtility::isRegisteredRecordTable('tt_content')
        ) {
            return false;
        }

        $record = $event->getRecord();

        return isset($record[Configuration::FIELD_STATUS])
            && is_numeric($record[Configuration::FIELD_STATUS])
            && $record[Configuration::FIELD_STATUS] > 0;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function decorate(PageContentPreviewRenderingEvent $event, Status $status, array $record): string
    {
        $commentsCount = PlannerUtility::hasComments($record)
            ? $this->commentRepository->countAllByRecord((int) $record['uid'], 'tt_content')
            : 0;

        return ViewUtility::render('Backend/Header/ContentElementStatusAccent', [
            'status' => [
                'title' => $status->getTitle(),
                'color' => $status->getColor(),
                'icon' => $status->getColoredIcon(),
            ],
            'comments' => [
                'count' => $commentsCount,
            ],
            'content' => $this->renderDefaultPreviewContent($event),
        ]);
    }

    /**
     * Renders the record's default (or CType-specific) preview content exactly the way core
     * would have without this listener, so decorating it does not regress any third-party
     * preview renderer registered for the CType via TCA.
     */
    private function renderDefaultPreviewContent(PageContentPreviewRenderingEvent $event): string
    {
        $context = $event->getPageLayoutContext();
        $table = $event->getTable();
        $record = $event->getRecord();

        $column = GeneralUtility::makeInstance(GridColumn::class, $context, [], $table);
        $item = GeneralUtility::makeInstance(GridColumnItem::class, $context, $column, $record, $table);

        $previewRenderer = GeneralUtility::makeInstance(StandardPreviewRendererResolver::class)
            ->resolveRendererFor($table, $record, $context->getPageId());

        return $previewRenderer->renderPageModulePreviewContent($item);
    }
}
