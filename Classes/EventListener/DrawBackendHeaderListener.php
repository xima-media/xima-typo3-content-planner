<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

/*
* https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Events/Events/Backend/ModifyPageLayoutContentEvent.html#modifypagelayoutcontentevent
*/

final class DrawBackendHeaderListener
{
    public function __construct(protected PageRepository $pageRepository, protected StatusRepository $statusRepository)
    {
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
        $status = ContentUtility::getStatus($pageInfo['tx_ximatypo3contentplanner_status']);

        if (!$status) {
            return;
        }

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename('EXT:' . Configuration::EXT_KEY . '/Resources/Private/Templates/Backend/PageHeader/PageHeaderInfo.html');

        /** @var PageRenderer $pageRenderer */
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/new-comment-modal.js');
        $pageRenderer->loadJavaScriptModule('@xima/ximatypo3contentplanner/comments-modal.js');
        $pageRenderer->addInlineLanguageLabelFile('EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf');

        $view->assignMultiple([
            'data' => $pageInfo,
            'assignee' => ContentUtility::getBackendUsernameById((int)$pageInfo['tx_ximatypo3contentplanner_assignee']),
            'icon' => $status->getColoredIcon(),
            'type' => Configuration::STATUS_COLOR_ALERTS[$status->getColor()],
            'status' => $status,
            'comments' => $pageInfo['tx_ximatypo3contentplanner_comments'] ? ContentUtility::getComments($id, 'pages') : [],
            'pid' => $id,
            'userid' => $GLOBALS['BE_USER']->user['uid'],
        ]);
        $event->addHeaderContent($view->render());
    }
}
