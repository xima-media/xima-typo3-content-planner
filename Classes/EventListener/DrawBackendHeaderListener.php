<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

/*
 * https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Events/Events/Backend/ModifyPageLayoutContentEvent.html#modifypagelayoutcontentevent
 */
final class DrawBackendHeaderListener
{
    public function __construct(protected PageRepository $pageRepository, protected FileRepository $fileRepository)
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

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename('EXT:xima_typo3_content_planner/Resources/Private/Templates/Backend/PageHeaderInfo.html');

        $view->assignMultiple([
            'data' => $pageInfo,
            'assignee' => ContentUtility::getBackendUsernameById((int)$pageInfo['tx_ximatypo3contentplanner_assignee']),
            'icon' => Configuration::STATUS_ICONS[$pageInfo['tx_ximatypo3contentplanner_status']],
            'comments' => $pageInfo['tx_ximatypo3contentplanner_comments'] ? ContentUtility::getPageComments($id) : [],
        ]);
        $event->addHeaderContent($view->render());
    }
}
