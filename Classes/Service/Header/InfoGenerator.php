<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "xima_typo3_content_planner".
 *
 * Copyright (C) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Xima\XimaTypo3ContentPlanner\Service\Header;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Core\Core\RequestId;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\UrlHelper;
use Xima\XimaTypo3ContentPlanner\Utility\ViewFactoryHelper;

class InfoGenerator
{
    private ?StatusRepository $statusRepository = null;
    private ?RecordRepository $recordRepository = null;
    private ?BackendUserRepository $backendUserRepository = null;
    private ?CommentRepository $commentRepository = null;

    public function __construct(
        private readonly RequestId $requestId
    ) {}

    public function generateStatusHeader(
        HeaderMode $mode,
        mixed $record = null,
        ?string $table = null,
        ?int $uid = null
    ): string|bool {
        if ($record === null && ($table === null || $uid === null)) {
            return false;
        }

        if ($record === null) {
            $record = $this->getRecordRepository()->findByUid(
                $table,
                $uid,
                ignoreVisibilityRestriction: true
            );
        }

        if (!(bool)$record) {
            return false;
        }

        $status = $this->getStatusRepository()->findByUid(
            $record['tx_ximatypo3contentplanner_status']
        );

        if (!$status instanceof Status) {
            return false;
        }

        return $this->renderStatusHeaderContentView(
            $mode,
            $record,
            $table,
            $status
        );
    }

    /**
    * @param array<string, mixed> $record
    * @throws RouteNotFoundException
    */
    private function renderStatusHeaderContentView(
        HeaderMode $mode,
        array $record,
        string $table,
        Status $status
    ): string {
        $content = ViewFactoryHelper::renderView(
            'Backend/Header/HeaderInfo.html',
            [
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
                        ? UrlHelper::assignToUser($table, $record['uid'])
                        : false,
                    'unassign' => self::checkUnassign($record)
                        ? UrlHelper::assignToUser($table, $record['uid'], unassign: true)
                        : null,
                ],
                'comments' => [
                    'items' => $this->getComments($record, $table),
                    'newCommentUri' => UrlHelper::getNewCommentUrl(
                        $table,
                        $record['uid']
                    ),
                    'editUri' => UrlHelper::getContentStatusPropertiesEditUrl(
                        $table,
                        $record['uid']
                    ),
                    'todoResolved' => ExtensionUtility::isFeatureEnabled(
                        Configuration::FEATURE_COMMENT_TODOS
                    ) ? $this->getCommentsTodoResolved($record, $table) : 0,
                    'todoTotal' => ExtensionUtility::isFeatureEnabled(
                        Configuration::FEATURE_COMMENT_TODOS
                    ) ? $this->getCommentsTodoTotal($record, $table) : 0,
                ],
                'contentElements' => $this->getContentElements($record, $table),
                'userid' => $GLOBALS['BE_USER']->user['uid'],
            ]
        );

        $content .= $this->addFrontendAssets($mode === HeaderMode::WEB_LAYOUT);

        return $content;
    }

    /**
     * @param array<string, mixed> $record
     * @throws Exception
     */
    private function getAssigneeUsername(array $record): string
    {
        if (!array_key_exists('tx_ximatypo3contentplanner_assignee', $record)) {
            return '';
        }
        return $this->getBackendUserRepository()->getUsernameByUid(
            (int)$record['tx_ximatypo3contentplanner_assignee']
        );
    }

    /**
    * @param array<string, mixed> $record
    */
    private function getAssignedToCurrentUser(array $record): bool
    {
        if (
            !array_key_exists('tx_ximatypo3contentplanner_assignee', $record) ||
            !ExtensionUtility::isFeatureEnabled(
                Configuration::FEATURE_CURRENT_ASSIGNEE_HIGHLIGHT
            )
        ) {
            return false;
        }

        return (int)$record['tx_ximatypo3contentplanner_assignee'] ===
            $GLOBALS['BE_USER']->user['uid'];
    }

    /**
    * @param array<string, mixed> $record
    */
    public static function checkAssignToCurrentUser(array $record): bool
    {
        if (
            !array_key_exists('tx_ximatypo3contentplanner_assignee', $record) ||
            !ExtensionUtility::isFeatureEnabled(
                Configuration::FEATURE_CURRENT_ASSIGNEE_HIGHLIGHT
            )
        ) {
            return false;
        }

        return $record['tx_ximatypo3contentplanner_assignee'] === null ||
            (int)$record['tx_ximatypo3contentplanner_assignee'] === 0 ||
            (int)$record['tx_ximatypo3contentplanner_assignee'] !==
                (int)$GLOBALS['BE_USER']->user['uid'];
    }

    /**
    * @param array<string, mixed> $record
    */
    public static function checkUnassign(array $record): bool
    {
        if (!array_key_exists('tx_ximatypo3contentplanner_assignee', $record)) {
            return false;
        }

        if (
            $record['tx_ximatypo3contentplanner_assignee'] !== null &&
            (int)$record['tx_ximatypo3contentplanner_assignee'] !== 0
        ) {
            return true;
        }
        return false;
    }

    /**
    * @param array<string, mixed> $record
    * @return array<int, array<string, mixed>>
    * @throws Exception
    */
    private function getComments(array $record, string $table): array
    {
        if (
            isset($record['tx_ximatypo3contentplanner_comments']) &&
            is_numeric($record['tx_ximatypo3contentplanner_comments']) &&
            $record['tx_ximatypo3contentplanner_comments'] > 0
        ) {
            return $this->getCommentRepository()->findAllByRecord(
                $record['uid'],
                $table,
                true
            );
        }

        return [];
    }

    /**
    * @param array<string, mixed> $record
    */
    private function getCommentsTodoResolved(array $record, string $table): int
    {
        if (
            isset($record['tx_ximatypo3contentplanner_comments']) &&
            is_numeric($record['tx_ximatypo3contentplanner_comments']) &&
            $record['tx_ximatypo3contentplanner_comments'] > 0
        ) {
            return $this->getCommentRepository()->countTodoAllByRecord(
                $record['uid'],
                $table
            );
        }

        return 0;
    }

    /**
    * @param array<string, mixed> $record
    */
    private function getCommentsTodoTotal(array $record, string $table): int
    {
        if (
            isset($record['tx_ximatypo3contentplanner_comments']) &&
            is_numeric($record['tx_ximatypo3contentplanner_comments']) &&
            $record['tx_ximatypo3contentplanner_comments'] > 0
        ) {
            return $this->getCommentRepository()->countTodoAllByRecord(
                $record['uid'],
                $table,
                'todo_total'
            );
        }

        return 0;
    }

    /**
    * @param array<string, mixed> $record
    */
    private function getPid(array $record, string $table): ?int
    {
        if ($table === 'pages') {
            return (int)$record['uid'];
        }
        if (array_key_exists('pid', $record)) {
            return (int)$record['pid'];
        }
        return null;
    }

    /**
    * @param array<string, mixed> $record
    * @return array<int, array<string, mixed>>|null
    * @throws Exception
    */
    private function getContentElements(array $record, string $table): ?array
    {
        if (
            ExtensionUtility::isRegisteredRecordTable('tt_content') &&
            $table === 'pages'
        ) {
            return $this->getRecordRepository()->findByPid(
                'tt_content',
                $record['uid'],
                false
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
                '@xima/ximatypo3contentplanner/create-and-edit-comment-modal.js'
            );
            $pageRenderer->loadJavaScriptModule(
                '@xima/ximatypo3contentplanner/comments-list-modal.js'
            );
            $pageRenderer->loadJavaScriptModule(
                '@xima/ximatypo3contentplanner/assignee-selection-modal.js'
            );
            $pageRenderer->addCssFile(
                'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Css/Header.css'
            );
            $pageRenderer->addInlineLanguageLabelFile(
                'EXT:' . Configuration::EXT_KEY .
                '/Resources/Private/Language/locallang.xlf'
            );
            return '';
        }
        $content = ExtensionUtility::getCssTag(
            'EXT:' . Configuration::EXT_KEY . '/Resources/Public/Css/Header.css',
            ['nonce' => $this->requestId->nonce]
        );
        $content .= ExtensionUtility::getJsTag(
            'EXT:' . Configuration::EXT_KEY .
            '/Resources/Public/JavaScript/comments-list-modal.js',
            ['nonce' => $this->requestId->nonce]
        );
        $content .= ExtensionUtility::getJsTag(
            'EXT:' . Configuration::EXT_KEY .
            '/Resources/Public/JavaScript/assignee-selection-modal.js',
            ['nonce' => $this->requestId->nonce]
        );
        return $content;
    }

    private function getStatusRepository(): StatusRepository
    {
        if ($this->statusRepository === null) {
            $this->statusRepository = GeneralUtility::makeInstance(StatusRepository::class);
        }
        return $this->statusRepository;
    }

    private function getRecordRepository(): RecordRepository
    {
        if ($this->recordRepository === null) {
            $this->recordRepository = GeneralUtility::makeInstance(RecordRepository::class);
        }
        return $this->recordRepository;
    }

    private function getBackendUserRepository(): BackendUserRepository
    {
        if ($this->backendUserRepository === null) {
            $this->backendUserRepository = GeneralUtility::makeInstance(BackendUserRepository::class);
        }
        return $this->backendUserRepository;
    }

    private function getCommentRepository(): CommentRepository
    {
        if ($this->commentRepository === null) {
            $this->commentRepository = GeneralUtility::makeInstance(CommentRepository::class);
        }
        return $this->commentRepository;
    }
}
