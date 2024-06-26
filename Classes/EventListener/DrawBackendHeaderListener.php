<?php

declare(strict_types=1);

namespace Xima\XimaContentPlanner\EventListener;

use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use Xima\XimaContentPlanner\Configuration;
use Xima\XimaContentPlanner\Utility\VisibilityUtility;

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
        if (!$pageInfo['tx_ximacontentplanner_status']) {
            return;
        }

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename('EXT:xima_content_planner/Resources/Private/Templates/Backend/PageHeaderInfo.html');

        $view->assignMultiple([
            'data' => $pageInfo,
            'assignee' => $this->getBackendUsernameById((int)$pageInfo['tx_ximacontentplanner_assignee']),
            'icon' => Configuration::STATUS_ICONS[$pageInfo['tx_ximacontentplanner_status']],
            'comments' => $pageInfo['tx_ximacontentplanner_comments'] ? $this->getPageComments($id) : [],
        ]);
        $event->addHeaderContent($view->render());
    }

    public static function getBackendUsernameById(?int $userId): string
    {
        if (!$userId) {
            return '';
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('be_users');

        $userRecord = $queryBuilder
            ->select('username')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($userId, \PDO::PARAM_INT))
            )
            ->executeQuery()->fetchOne();

        if ($userRecord) {
            return htmlspecialchars($userRecord, ENT_QUOTES, 'UTF-8');
        }

        return '';
    }

    private function getPageComments(int $pageId): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_ximacontentplanner_comment');

        $comments = $queryBuilder
            ->select('*')
            ->from('tx_ximacontentplanner_comment')
            ->where(
                $queryBuilder->expr()->eq('foreign_uid', $queryBuilder->createNamedParameter($pageId, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('foreign_table', $queryBuilder->createNamedParameter('pages', \PDO::PARAM_STR))
            )
            ->executeQuery()->fetchAllAssociative();

        foreach ($comments as &$comment) {
            $comment['date'] = date('d.m.Y H:i', $comment['crdate']);
            $comment['user'] = $this->getBackendUsernameById($comment['author']);
        }
        return $comments;
    }
}
