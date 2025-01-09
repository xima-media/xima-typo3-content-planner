<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

/*
* https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Events/Events/Backend/ModifyPageLayoutContentEvent.html#modifypagelayoutcontentevent
*/

final class DrawBackendHeaderListener
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly StatusRepository $statusRepository,
        private readonly CommentRepository $commentRepository,
        private readonly BackendUserRepository $backendUserRepository,
        private readonly RecordRepository $recordRepository
    ) {
    }

    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        if (!VisibilityUtility::checkContentStatusVisibility()) {
            return;
        }

        $id = (int)($event->getRequest()->getQueryParams()['id'] ?? 0);
        $pageInfo = $this->pageRepository->getPage($id);
        if (empty($pageInfo)) {
            return;
        }
        if (!$pageInfo['tx_ximatypo3contentplanner_status']) {
            return;
        }
        $status = $this->statusRepository->findByUid($pageInfo['tx_ximatypo3contentplanner_status']);

        if (!$status) {
            return;
        }

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename('EXT:' . Configuration::EXT_KEY . '/Resources/Private/Templates/Backend/PageHeader/PageHeaderInfo.html');

        /** @var PageRenderer $pageRenderer */
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/new-comment-modal.js');
        $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/comments-modal.js');
        $pageRenderer->addCssFile('EXT:' . Configuration::EXT_KEY . '/Resources/Public/Css/PageHeader.css');
        $pageRenderer->addInlineLanguageLabelFile('EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf');

        $view->assignMultiple([
            'data' => $pageInfo,
            'assignee' => $this->backendUserRepository->getUsernameByUid((int)$pageInfo['tx_ximatypo3contentplanner_assignee']),
            'icon' => $status->getColoredIcon(),
            'status' => $status,
            'comments' => $pageInfo['tx_ximatypo3contentplanner_comments'] ? $this->commentRepository->findAllByRecord($id, 'pages') : [],
            'pid' => $id,
            'userid' => $GLOBALS['BE_USER']->user['uid'],
            'contentElements' => $this->recordRepository->findByPid('tt_content', $id, false),
        ]);
        $event->addHeaderContent($view->render());
    }
}
