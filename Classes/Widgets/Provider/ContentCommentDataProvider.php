<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets\Provider;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;

class ContentCommentDataProvider implements ListDataProviderInterface
{
    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getItems(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_ximatypo3contentplanner_comment');

        $query = $queryBuilder
            ->select(
                'c.uid',
                'c.content as content',
                'c.tstamp as tstamp',
                'c.author as author',
                'c.foreign_uid as pid',
                'p.title as title',
                'p.tx_ximatypo3contentplanner_status as status',
            )
            ->from('tx_ximatypo3contentplanner_comment', 'c')
            ->leftJoin('c', 'pages', 'p', 'c.foreign_uid = p.uid')
            ->setMaxResults(20)
            ->orderBy('tstamp', 'DESC');

        $items = [];
        $results = $query->executeQuery()
            ->fetchAllAssociative();

        foreach ($results as $result) {
            try {
                $items[] = $this->createListItem($result);
            } catch (\Exception $e) {
            }
        }

        return $items;
    }

    private function createListItem(array $result): array
    {
        return [
            'uid' => $result['uid'],
            'pid' => $result['pid'],
            'title' => $result['title'],
            'tstamp' => $result['tstamp'],
            'content' => $result['content'],
            'status' => $result['status'],
            'status_icon' => Configuration::STATUS_ICONS[$result['status']],
            'author' => (int)$result['author'],
            'author_name' => ContentUtility::getBackendUsernameById((int)$result['author']),
        ];
    }
}
