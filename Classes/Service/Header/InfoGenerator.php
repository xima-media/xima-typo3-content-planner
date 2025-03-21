<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Service\Header;

use TYPO3\CMS\Core\Core\RequestId;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\UrlHelper;

class InfoGenerator
{
    private ?StatusRepository $statusRepository = null;
    private ?RecordRepository $recordRepository = null;
    private ?BackendUserRepository $backendUserRepository = null;
    private ?CommentRepository $commentRepository = null;

    public function __construct(
        private readonly RequestId $requestId
    ) {
    }

    public function generateStatusHeader(HeaderMode $mode, mixed $record = null, ?string $table = null, ?int $uid = null): string|bool
    {
        if ($record === null && ($table === null || $uid === null)) {
            return false;
        }

        if ($record === null) {
            $record = $this->getRecordRepository()->findByUid($table, $uid, ignoreHiddenRestriction: true);
        }

        if (!$record) {
            return false;
        }

        $status = $this->getStatusRepository()->findByUid($record['tx_ximatypo3contentplanner_status']);

        if (!$status) {
            return false;
        }

        $additionalContent = $this->renderStatusHeaderContentView(
            $mode,
            $record,
            $table,
            $status
        );

        return $additionalContent;
    }

    private function renderStatusHeaderContentView(HeaderMode $mode, array $record, string $table, Status $status): string
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename('EXT:' . Configuration::EXT_KEY . '/Resources/Private/Templates/Backend/Header/HeaderInfo.html');

        $view->assignMultiple([
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
                'assignToCurrentUser' => $this->checkAssignToCurrentUser($record, $table) ? UrlHelper::assignToUser($table, $record['uid']) : false,
                'unassign' => $this->checkUnassign($record) ? UrlHelper::assignToUser($table, $record['uid'], unassign: true) : null,
            ],
            'comments' => [
                'items' => $this->getComments($record, $table),
                'newCommentUri' => UrlHelper::getNewCommentUrl($table, $record['uid']),
                'editUri' => UrlHelper::getContentStatusPropertiesEditUrl($table, $record['uid']),
            ],
            'contentElements' => $this->getContentElements($record, $table),
            'userid' => $GLOBALS['BE_USER']->user['uid'],
        ]);

        $content = $view->render();
        $content .= $this->addFrontendAssets($mode === HeaderMode::WEB_LAYOUT);

        return $content;
    }

    private function getAssigneeUsername(array $record): string
    {
        if (!array_key_exists('tx_ximatypo3contentplanner_assignee', $record)) {
            return '';
        }
        return $this->getBackendUserRepository()->getUsernameByUid((int)$record['tx_ximatypo3contentplanner_assignee']);
    }

    private function getAssignedToCurrentUser(array $record): bool
    {
        if (!array_key_exists('tx_ximatypo3contentplanner_assignee', $record) || !ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_CURRENT_ASSIGNEE_HIGHLIGHT)) {
            return false;
        }
        return (int)$record['tx_ximatypo3contentplanner_assignee'] === $GLOBALS['BE_USER']->user['uid'];
    }

    private function checkAssignToCurrentUser(array $record, string $table): bool
    {
        if (!array_key_exists('tx_ximatypo3contentplanner_assignee', $record) || !ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_CURRENT_ASSIGNEE_HIGHLIGHT)) {
            return false;
        }
        return $record['tx_ximatypo3contentplanner_assignee'] === null || $record['tx_ximatypo3contentplanner_assignee'] !== $GLOBALS['BE_USER']->user['uid'];
    }

    private function checkUnassign(array $record): bool
    {
        if (!array_key_exists('tx_ximatypo3contentplanner_assignee', $record)) {
            return false;
        }

        if ($record['tx_ximatypo3contentplanner_assignee'] !== null && $record['tx_ximatypo3contentplanner_assignee'] !== 0) {
            return true;
        }
        return false;
    }

    private function getComments(array $record, string $table): array
    {
        return $record['tx_ximatypo3contentplanner_comments'] ? $this->getCommentRepository()->findAllByRecord($record['uid'], $table, true) : [];
    }

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

    private function getContentElements(array $record, string $table): ?array
    {
        return ExtensionUtility::isRegisteredRecordTable('tt_content') && $table === 'pages' ? $this->getRecordRepository()->findByPid('tt_content', $record['uid'], false) : null;
    }

    private function addFrontendAssets(bool $usePageRenderer = true): string
    {
        if ($usePageRenderer) {
            /** @var PageRenderer $pageRenderer */
            $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
            $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/new-comment-modal.js');
            $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/comments-modal.js');
            $pageRenderer->addCssFile('EXT:' . Configuration::EXT_KEY . '/Resources/Public/Css/Header.css');
            $pageRenderer->addInlineLanguageLabelFile('EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf');
            return '';
        }
        $content = ExtensionUtility::getCssTag('EXT:' . Configuration::EXT_KEY . '/Resources/Public/Css/Header.css', ['nonce' => $this->requestId->nonce]);
        $content .= ExtensionUtility::getJsTag('EXT:' . Configuration::EXT_KEY . '/Resources/Public/JavaScript/comments-modal.js', ['nonce' => $this->requestId->nonce]);
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
