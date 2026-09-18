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

namespace Xima\XimaTypo3ContentPlanner\Service\Header;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\RequestId;
use TYPO3\CMS\Core\Page\PageRenderer;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository, FolderStatusRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Service\WatcherPresentationService;
use Xima\XimaTypo3ContentPlanner\Utility\{ExtensionUtility, PlannerUtility};
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\{AssetUtility, IconUtility, ViewUtility};
use Xima\XimaTypo3ContentPlanner\Utility\Routing\UrlUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function array_key_exists;
use function is_array;
use function max;

/**
 * InfoGenerator.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class InfoGenerator
{
    public function __construct(
        private readonly RequestId $requestId,
        private readonly StatusRepository $statusRepository,
        private readonly RecordRepository $recordRepository,
        private readonly BackendUserRepository $backendUserRepository,
        private readonly CommentRepository $commentRepository,
        private readonly FolderStatusRepository $folderStatusRepository,
        private readonly WatcherPresentationService $watcherPresentationService,
        private readonly PageRenderer $pageRenderer,
    ) {}

    public function generateStatusHeader(
        HeaderMode $mode,
        mixed $record = null,
        ?string $table = null,
        ?int $uid = null,
        bool $compact = false,
    ): string|bool {
        if (null === $record && (null === $table || null === $uid)) {
            return false;
        }

        $record ??= $this->recordRepository->findByUid(
            $table,
            $uid,
            true,
        );

        if (!(bool) $record) {
            return false;
        }

        $status = $this->statusRepository->findByUid(
            $record[Configuration::FIELD_STATUS],
        );

        if (!$status instanceof Status) {
            return false;
        }

        return $this->renderStatusHeaderContentView(
            $mode,
            $record,
            $table,
            $status,
            $compact,
        );
    }

    /**
     * Generate status header for a folder.
     *
     * @throws Exception
     */
    public function generateFolderStatusHeader(
        string $combinedIdentifier,
        string $folderName,
    ): string|bool {
        $folderRecord = $this->folderStatusRepository->findByCombinedIdentifier($combinedIdentifier);

        if (!is_array($folderRecord) || !isset($folderRecord[Configuration::FIELD_STATUS]) || 0 === (int) $folderRecord[Configuration::FIELD_STATUS]) {
            return false;
        }

        $status = $this->statusRepository->findByUid((int) $folderRecord[Configuration::FIELD_STATUS]);

        if (!$status instanceof Status) {
            return false;
        }

        return $this->renderFolderStatusHeaderContentView(
            $folderRecord,
            $combinedIdentifier,
            $folderName,
            $status,
        );
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function checkAssignToCurrentUser(array $record): bool
    {
        if (
            !array_key_exists(Configuration::FIELD_ASSIGNEE, $record)
            || !ExtensionUtility::isFeatureEnabled(
                Configuration::FEATURE_CURRENT_ASSIGNEE_HIGHLIGHT,
            )
        ) {
            return false;
        }

        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];

        return null === $record[Configuration::FIELD_ASSIGNEE]
            || 0 === (int) $record[Configuration::FIELD_ASSIGNEE]
            || (int) $record[Configuration::FIELD_ASSIGNEE] !==
                (int) ($backendUser->user['uid'] ?? 0);
    }

    /**
     * Check if the current user can unassign a record.
     * assign-others: can always unassign. assign-self: can only unassign yourself.
     *
     * @param array<string, mixed> $record
     */
    public static function canUnassignRecord(array $record): bool
    {
        if (PermissionUtility::canAssignOthers()) {
            return true;
        }

        if (!PermissionUtility::canAssignSelf()) {
            return false;
        }

        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];
        $currentUserId = (int) ($backendUser->user['uid'] ?? 0);
        $assigneeId = (int) ($record[Configuration::FIELD_ASSIGNEE] ?? 0);

        return $currentUserId > 0 && $currentUserId === $assigneeId;
    }

    /**
     * @param array<string, mixed> $record
     */
    public static function checkUnassign(array $record): bool
    {
        if (!array_key_exists(Configuration::FIELD_ASSIGNEE, $record)) {
            return false;
        }

        if (
            null !== $record[Configuration::FIELD_ASSIGNEE]
            && 0 !== (int) $record[Configuration::FIELD_ASSIGNEE]
        ) {
            return true;
        }

        return false;
    }

    /**
     * Loads the JS modules and CSS shared by every place that renders the status/assignee/
     * comment trio via PageRenderer: the "banner" HeaderInfo partial (see addFrontendAssets()
     * above) and, since CP-25 (#324), the doc header chip trio added by
     * ModifyButtonBarEventListener in "chip" headerDisplayMode.
     */
    public static function loadHeaderAssets(PageRenderer $pageRenderer): void
    {
        $pageRenderer->loadJavaScriptModule(
            Configuration::JAVASCRIPT_MODULE_PREFIX.'comments-list-modal.js',
        );
        $pageRenderer->loadJavaScriptModule(
            Configuration::JAVASCRIPT_MODULE_PREFIX.'comments-reload-content.js',
        );
        $pageRenderer->loadJavaScriptModule(
            Configuration::JAVASCRIPT_MODULE_PREFIX.'assignee-selection-modal.js',
        );
        $pageRenderer->loadJavaScriptModule(
            Configuration::JAVASCRIPT_MODULE_PREFIX.'watch-toggle.js',
        );
        $pageRenderer->loadJavaScriptModule(
            Configuration::JAVASCRIPT_MODULE_PREFIX.'header-tooltips.js',
        );
        $pageRenderer->addCssFile(
            'EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Header.css',
        );
        // Preloaded here rather than left to load only when the record modal's AJAX response
        // first injects its <link> tags (RecordController::commentsAction()/
        // assigneeSelectionAction()) - otherwise the modal's first open in a session renders
        // its content unstyled for a moment while the browser fetches these for the first time.
        $pageRenderer->addCssFile(
            'EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/RecordModal.css',
        );
        $pageRenderer->addCssFile(
            'EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Comments.css',
        );
        $pageRenderer->addCssFile(
            'EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Assignee.css',
        );
        $pageRenderer->addInlineLanguageLabelFile(
            'EXT:'.Configuration::EXT_KEY.
            '/Resources/Private/Language/locallang.xlf',
        );
    }

    /**
     * Shared with ModifyButtonBarEventListener's doc header chip trio (CP-25 / #324), so the
     * "assigned to me" check cannot drift out of sync between the banner and the chip.
     *
     * @param array<string, mixed> $record
     */
    public static function getAssignedToCurrentUser(array $record): bool
    {
        if (
            !array_key_exists(Configuration::FIELD_ASSIGNEE, $record)
            || !ExtensionUtility::isFeatureEnabled(
                Configuration::FEATURE_CURRENT_ASSIGNEE_HIGHLIGHT,
            )
        ) {
            return false;
        }

        return (int) $record[Configuration::FIELD_ASSIGNEE] ===
            self::getBackendUserId();
    }

    /**
     * @param array<string, mixed> $record
     *
     * @throws RouteNotFoundException
     */
    private function renderStatusHeaderContentView(
        HeaderMode $mode,
        array $record,
        string $table,
        Status $status,
        bool $compact = false,
    ): string {
        $content = ViewUtility::render('Backend/Header/HeaderInfo', [
            'mode' => $mode->value,
            'compact' => HeaderMode::CONTENT_ELEMENT === $mode || $compact,
            'data' => $record,
            'table' => $table,
            'pid' => $this->getPid($record, $table),
            'status' => [
                'title' => $status->getTitle(),
                'color' => $status->getColor(),
                'icon' => $status->getColoredIcon(),
            ],
            'assignee' => [
                'username' => $this->getAssigneeUsername($record),
                'assignedToCurrentUser' => self::getAssignedToCurrentUser($record),
                'assignToCurrentUser' => PermissionUtility::canAssignSelf() && self::checkAssignToCurrentUser($record)
                    ? UrlUtility::assignToUser($table, $record['uid'])
                    : false,
                'unassign' => self::canUnassignRecord($record) && self::checkUnassign($record)
                    ? UrlUtility::assignToUser($table, $record['uid'], null, true)
                    : null,
            ],
            'comments' => [
                'items' => $this->getComments($record, $table),
                'count' => $this->commentRepository->countAllByRecord((int) $record['uid'], $table),
                'newCommentUri' => PermissionUtility::canCreateComment()
                    ? UrlUtility::getNewCommentUrl($table, $record['uid'])
                    : '',
                'editUri' => UrlUtility::getContentStatusPropertiesEditUrl(
                    $table,
                    $record['uid'],
                ),
                ...$this->getTodoCounts($record, $table),
            ],
            'contentElements' => $this->getContentElements($record, $table),
            'userid' => self::getBackendUserId(),
            'watch' => $this->watcherPresentationService->build($table, (int) $record['uid'], self::getBackendUserId()),
        ]);

        // CONTENT_ELEMENT is rendered from ContentElementHeaderModifier, a middleware that
        // post-processes the response *after* $handler->handle() (and therefore after
        // PageRenderer::render()) has already run - registering assets on PageRenderer at
        // that point would never reach the response. It gets the inline tags instead (like
        // EDIT does for the same reason), same as WEB_LAYOUT/WEB_LIST get via PageRenderer
        // because those run through a PSR-14 event fired inside the controller, before render.
        // The "docked" headerDisplayMode is the same trap in WEB_LAYOUT clothing: it is
        // rendered from DockedHeaderModifier, a middleware exactly like ContentElementHeaderModifier,
        // not from DrawBackendHeaderListener's pre-render PSR-14 event - so it needs the inline
        // tags too, even though $mode is WEB_LAYOUT. $compact is only ever true for that call.
        $content .= $this->addFrontendAssets(HeaderMode::WEB_LAYOUT === $mode && !$compact);

        return $content;
    }

    /**
     * @param array<string, mixed> $folderRecord
     *
     * @throws Exception|RouteNotFoundException
     */
    private function renderFolderStatusHeaderContentView(
        array $folderRecord,
        string $combinedIdentifier,
        string $folderName,
        Status $status,
    ): string {
        $table = Configuration::TABLE_FOLDER;
        $uid = (int) $folderRecord['uid'];

        $content = ViewUtility::render('Backend/Header/HeaderInfo', [
            'mode' => HeaderMode::FILE_LIST->value,
            'compact' => false,
            'data' => $folderRecord,
            'table' => $table,
            'pid' => null,
            'folderIdentifier' => $combinedIdentifier,
            'folderName' => $folderName,
            'status' => [
                'title' => $status->getTitle(),
                'color' => $status->getColor(),
                'icon' => $status->getColoredIcon(),
            ],
            'assignee' => [
                'username' => $this->getAssigneeUsername($folderRecord),
                'assignedToCurrentUser' => self::getAssignedToCurrentUser($folderRecord),
                'assignToCurrentUser' => PermissionUtility::canAssignSelf() && self::checkAssignToCurrentUser($folderRecord)
                    ? UrlUtility::assignToUser($table, $uid)
                    : false,
                'unassign' => self::canUnassignRecord($folderRecord) && self::checkUnassign($folderRecord)
                    ? UrlUtility::assignToUser($table, $uid, null, true)
                    : null,
            ],
            'comments' => [
                'items' => $this->getFolderComments($folderRecord),
                'count' => $this->commentRepository->countAllByRecord($uid, $table),
                'newCommentUri' => PermissionUtility::canCreateComment()
                    ? UrlUtility::getNewCommentUrl($table, $uid)
                    : '',
                'editUri' => UrlUtility::getContentStatusPropertiesEditUrl($table, $uid),
                ...$this->getTodoCounts($folderRecord, $table),
            ],
            'contentElements' => null,
            'userid' => self::getBackendUserId(),
        ]);

        $content .= $this->addFrontendAssets(false);

        return $content;
    }

    /**
     * @param array<string, mixed> $record
     *
     * @throws Exception
     */
    private function getAssigneeUsername(array $record): string
    {
        if (!array_key_exists(Configuration::FIELD_ASSIGNEE, $record)) {
            return '';
        }

        return $this->backendUserRepository->getUsernameByUid(
            (int) $record[Configuration::FIELD_ASSIGNEE],
        );
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    private function getComments(array $record, string $table): array
    {
        if (PlannerUtility::hasComments($record)) {
            return $this->commentRepository->findAllByRecord($record['uid'], $table, true);
        }

        return [];
    }

    /**
     * The header shows `todoOpen` as its badge and keeps resolved/total for the tooltip, so all
     * three are derived from the same pair of queries instead of being recomputed per field.
     *
     * @param array<string, mixed> $record
     *
     * @return array{todoResolved: int, todoTotal: int, todoOpen: int}
     */
    private function getTodoCounts(array $record, string $table): array
    {
        if (!ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_COMMENT_TODOS)) {
            return ['todoResolved' => 0, 'todoTotal' => 0, 'todoOpen' => 0];
        }

        $resolved = $this->getCommentsTodoResolved($record, $table);
        $total = $this->getCommentsTodoTotal($record, $table);

        return [
            'todoResolved' => $resolved,
            'todoTotal' => $total,
            'todoOpen' => max(0, $total - $resolved),
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function getCommentsTodoResolved(array $record, string $table): int
    {
        if (PlannerUtility::hasComments($record)) {
            return $this->commentRepository->countTodoAllByRecord((int) $record['uid'], $table);
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function getCommentsTodoTotal(array $record, string $table): int
    {
        if (PlannerUtility::hasComments($record)) {
            return $this->commentRepository->countTodoAllByRecord((int) $record['uid'], $table, 'todo_total');
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function getPid(array $record, string $table): ?int
    {
        if ('pages' === $table) {
            return (int) $record['uid'];
        }
        if (array_key_exists('pid', $record)) {
            return (int) $record['pid'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array<int, array<string, mixed>>|null
     *
     * @throws Exception
     */
    private function getContentElements(array $record, string $table): ?array
    {
        if (
            ExtensionUtility::isRegisteredRecordTable('tt_content')
            && 'pages' === $table
        ) {
            $contentElements = $this->recordRepository->findByPid(
                'tt_content',
                $record['uid'],
                false,
            );

            // The CE hint dropdown leads with the element's own CType icon (e.g. "text",
            // "bullets") rather than its status colour, so the list reads by content type
            // first - the status icon further along the row already carries the status.
            foreach ($contentElements as &$contentElement) {
                $contentElement['typeIcon'] = IconUtility::getIconByRecord('tt_content', $contentElement, true);
            }

            return $contentElements;
        }

        return null;
    }

    private static function getBackendUserId(): int
    {
        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];

        return (int) ($backendUser->user['uid'] ?? 0);
    }

    private function addFrontendAssets(bool $usePageRenderer = true): string
    {
        if ($usePageRenderer) {
            self::loadHeaderAssets($this->pageRenderer);

            return '';
        }
        $content = AssetUtility::getCssTag(
            'EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Header.css',
            ['nonce' => $this->requestId->nonce],
        );
        // Preloaded for the same reason as in loadHeaderAssets() above: without this, the
        // record modal's first open in a session renders unstyled until its own AJAX response
        // injects these for the first time.
        $content .= AssetUtility::getCssTag(
            'EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/RecordModal.css',
            ['nonce' => $this->requestId->nonce],
        );
        $content .= AssetUtility::getCssTag(
            'EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Comments.css',
            ['nonce' => $this->requestId->nonce],
        );
        $content .= AssetUtility::getCssTag(
            'EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Assignee.css',
            ['nonce' => $this->requestId->nonce],
        );
        $content .= AssetUtility::getJsTag(
            'EXT:'.Configuration::EXT_KEY.
            '/Resources/Public/JavaScript/comments-list-modal.js',
            ['nonce' => $this->requestId->nonce],
        );
        $content .= AssetUtility::getJsTag(
            'EXT:'.Configuration::EXT_KEY.
            '/Resources/Public/JavaScript/assignee-selection-modal.js',
            ['nonce' => $this->requestId->nonce],
        );
        $content .= AssetUtility::getJsTag(
            'EXT:'.Configuration::EXT_KEY.
            '/Resources/Public/JavaScript/watch-toggle.js',
            ['nonce' => $this->requestId->nonce],
        );
        $content .= AssetUtility::getJsTag(
            'EXT:'.Configuration::EXT_KEY.
            '/Resources/Public/JavaScript/header-tooltips.js',
            ['nonce' => $this->requestId->nonce],
        );

        return $content;
    }

    /**
     * @param array<string, mixed> $folderRecord
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    private function getFolderComments(array $folderRecord): array
    {
        if (PlannerUtility::hasComments($folderRecord)) {
            return $this->commentRepository->findAllByRecord((int) $folderRecord['uid'], Configuration::TABLE_FOLDER, true);
        }

        return [];
    }
}
