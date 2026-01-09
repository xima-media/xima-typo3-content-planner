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
use TYPO3\CMS\Core\Core\RequestId;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository, FolderStatusRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Utility\{ExtensionUtility, PlannerUtility};
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\{AssetUtility, ViewUtility};
use Xima\XimaTypo3ContentPlanner\Utility\Routing\UrlUtility;

use function array_key_exists;
use function is_array;

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
    ) {}

    public function generateStatusHeader(
        HeaderMode $mode,
        mixed $record = null,
        ?string $table = null,
        ?int $uid = null,
    ): string|bool {
        if (null === $record && (null === $table || null === $uid)) {
            return false;
        }

        if (null === $record) {
            $record = $this->recordRepository->findByUid(
                $table,
                $uid,
                ignoreVisibilityRestriction: true,
            );
        }

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

        return null === $record[Configuration::FIELD_ASSIGNEE]
            || 0 === (int) $record[Configuration::FIELD_ASSIGNEE]
            || (int) $record[Configuration::FIELD_ASSIGNEE] !==
                (int) $GLOBALS['BE_USER']->user['uid'];
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
     * @param array<string, mixed> $record
     *
     * @throws RouteNotFoundException
     */
    private function renderStatusHeaderContentView(
        HeaderMode $mode,
        array $record,
        string $table,
        Status $status,
    ): string {
        $content = ViewUtility::render('Backend/Header/HeaderInfo', [
            'mode' => $mode->value,
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
                'assignedToCurrentUser' => $this->getAssignedToCurrentUser($record),
                'assignToCurrentUser' => self::checkAssignToCurrentUser($record)
                    ? UrlUtility::assignToUser($table, $record['uid'])
                    : false,
                'unassign' => self::checkUnassign($record)
                    ? UrlUtility::assignToUser($table, $record['uid'], unassign: true)
                    : null,
            ],
            'comments' => [
                'items' => $this->getComments($record, $table),
                'newCommentUri' => UrlUtility::getNewCommentUrl(
                    $table,
                    $record['uid'],
                ),
                'editUri' => UrlUtility::getContentStatusPropertiesEditUrl(
                    $table,
                    $record['uid'],
                ),
                'todoResolved' => ExtensionUtility::isFeatureEnabled(
                    Configuration::FEATURE_COMMENT_TODOS,
                ) ? $this->getCommentsTodoResolved($record, $table) : 0,
                'todoTotal' => ExtensionUtility::isFeatureEnabled(
                    Configuration::FEATURE_COMMENT_TODOS,
                ) ? $this->getCommentsTodoTotal($record, $table) : 0,
            ],
            'contentElements' => $this->getContentElements($record, $table),
            'userid' => $GLOBALS['BE_USER']->user['uid'],
        ]);

        $content .= $this->addFrontendAssets(HeaderMode::WEB_LAYOUT === $mode);

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
                'assignedToCurrentUser' => $this->getAssignedToCurrentUser($folderRecord),
                'assignToCurrentUser' => self::checkAssignToCurrentUser($folderRecord)
                    ? UrlUtility::assignToUser($table, $uid)
                    : false,
                'unassign' => self::checkUnassign($folderRecord)
                    ? UrlUtility::assignToUser($table, $uid, unassign: true)
                    : null,
            ],
            'comments' => [
                'items' => $this->getFolderComments($folderRecord),
                'newCommentUri' => UrlUtility::getNewCommentUrl($table, $uid),
                'editUri' => UrlUtility::getContentStatusPropertiesEditUrl($table, $uid),
                'todoResolved' => ExtensionUtility::isFeatureEnabled(
                    Configuration::FEATURE_COMMENT_TODOS,
                ) ? $this->getCommentsTodoResolved($folderRecord, $table) : 0,
                'todoTotal' => ExtensionUtility::isFeatureEnabled(
                    Configuration::FEATURE_COMMENT_TODOS,
                ) ? $this->getCommentsTodoTotal($folderRecord, $table) : 0,
            ],
            'contentElements' => null,
            'userid' => $GLOBALS['BE_USER']->user['uid'],
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
     */
    private function getAssignedToCurrentUser(array $record): bool
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
            $GLOBALS['BE_USER']->user['uid'];
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
            return $this->recordRepository->findByPid(
                'tt_content',
                $record['uid'],
                false,
            );
        }

        return null;
    }

    private function addFrontendAssets(bool $usePageRenderer = true): string
    {
        if ($usePageRenderer) {
            /** @var PageRenderer $pageRenderer */
            $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
            $pageRenderer->loadJavaScriptModule(
                '@xima/ximatypo3contentplanner/create-and-edit-comment-modal.js',
            );
            $pageRenderer->loadJavaScriptModule(
                '@xima/ximatypo3contentplanner/comments-list-modal.js',
            );
            $pageRenderer->loadJavaScriptModule(
                '@xima/ximatypo3contentplanner/assignee-selection-modal.js',
            );
            $pageRenderer->addCssFile(
                'EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Header.css',
            );
            $pageRenderer->addInlineLanguageLabelFile(
                'EXT:'.Configuration::EXT_KEY.
                '/Resources/Private/Language/locallang.xlf',
            );

            return '';
        }
        $content = AssetUtility::getCssTag(
            'EXT:'.Configuration::EXT_KEY.'/Resources/Public/Css/Header.css',
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
