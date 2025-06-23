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
            $record = $this->getRecordRepository()->findByUid($table, $uid, ignoreVisibilityRestriction: true);
        }

        if (!(bool)$record) {
            return false;
        }

        $status = $this->getStatusRepository()->findByUid($record['tx_ximatypo3contentplanner_status']);

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

    private function renderStatusHeaderContentView(HeaderMode $mode, array $record, string $table, Status $status): string
    {
        // @ToDo: StandaloneView is deprecated and should be replaced with FluidView in TYPO3 v13
        $view = GeneralUtility::makeInstance(StandaloneView::class); // @phpstan-ignore-line classConstant.deprecatedClass
        // @ToDo: setTemplatePathAndFilename is deprecated and should be replaced with ViewFactoryInterface
        $view->setTemplatePathAndFilename('EXT:' . Configuration::EXT_KEY . '/Resources/Private/Templates/Backend/Header/HeaderInfo.html'); // @phpstan-ignore-line method.deprecatedClass

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
                'assignToCurrentUser' => self::checkAssignToCurrentUser($record) ? UrlHelper::assignToUser($table, $record['uid']) : false,
                'unassign' => self::checkUnassign($record) ? UrlHelper::assignToUser($table, $record['uid'], unassign: true) : null,
            ],
            'comments' => [
                'items' => $this->getComments($record, $table),
                'newCommentUri' => UrlHelper::getNewCommentUrl($table, $record['uid']),
                'editUri' => UrlHelper::getContentStatusPropertiesEditUrl($table, $record['uid']),
                'todoResolved' => ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_COMMENT_TODOS) ? $this->getCommentsTodoResolved($record, $table) : 0,
                'todoTotal' => ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_COMMENT_TODOS) ? $this->getCommentsTodoTotal($record, $table) : 0,
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

    public static function checkAssignToCurrentUser(array $record): bool
    {
        if (!array_key_exists('tx_ximatypo3contentplanner_assignee', $record) || !ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_CURRENT_ASSIGNEE_HIGHLIGHT)) {
            return false;
        }
        return $record['tx_ximatypo3contentplanner_assignee'] === null
            || (int)$record['tx_ximatypo3contentplanner_assignee'] === 0
            || (int)$record['tx_ximatypo3contentplanner_assignee'] !== (int)$GLOBALS['BE_USER']->user['uid'];
    }

    public static function checkUnassign(array $record): bool
    {
        if (!array_key_exists('tx_ximatypo3contentplanner_assignee', $record)) {
            return false;
        }

        if ($record['tx_ximatypo3contentplanner_assignee'] !== null && (int)$record['tx_ximatypo3contentplanner_assignee'] !== 0) {
            return true;
        }
        return false;
    }

    private function getComments(array $record, string $table): array
    {
        return $record['tx_ximatypo3contentplanner_comments'] ? $this->getCommentRepository()->findAllByRecord($record['uid'], $table, true) : [];
    }

    private function getCommentsTodoResolved(array $record, string $table): int
    {
        return $record['tx_ximatypo3contentplanner_comments'] ? $this->getCommentRepository()->countTodoAllByRecord($record['uid'], $table) : 0;
    }

    private function getCommentsTodoTotal(array $record, string $table): int
    {
        return $record['tx_ximatypo3contentplanner_comments'] ? $this->getCommentRepository()->countTodoAllByRecord($record['uid'], $table, 'todo_total') : 0;
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
            $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/create-and-edit-comment-modal.js');
            $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/comments-list-modal.js');
            $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/assignee-selection-modal.js');
            $pageRenderer->addCssFile('EXT:' . Configuration::EXT_KEY . '/Resources/Public/Css/Header.css');
            $pageRenderer->addInlineLanguageLabelFile('EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf');
            return '';
        }
        $content = ExtensionUtility::getCssTag('EXT:' . Configuration::EXT_KEY . '/Resources/Public/Css/Header.css', ['nonce' => $this->requestId->nonce]);
        $content .= ExtensionUtility::getJsTag('EXT:' . Configuration::EXT_KEY . '/Resources/Public/JavaScript/comments-list-modal.js', ['nonce' => $this->requestId->nonce]);
        $content .= ExtensionUtility::getJsTag('EXT:' . Configuration::EXT_KEY . '/Resources/Public/JavaScript/assignee-selection-modal.js', ['nonce' => $this->requestId->nonce]);
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
