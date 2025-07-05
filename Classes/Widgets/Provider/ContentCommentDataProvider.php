<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets\Provider;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\CommentItem;

class ContentCommentDataProvider implements ListDataProviderInterface
{
    public function __construct(private readonly ConnectionPool $connectionPool)
    {
    }
    /**
    * @return CommentItem[]
    * @throws \Doctrine\DBAL\Exception
    */
    public function getItems(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_ximatypo3contentplanner_comment');

        $query = $queryBuilder
            ->select(
                'c.uid',
                'c.content',
                'c.author',
                'c.tstamp',
                'c.foreign_uid',
                'c.foreign_table',
            )
            ->from('tx_ximatypo3contentplanner_comment', 'c')
            ->setMaxResults(10)
            ->orderBy('tstamp', 'DESC');

        $items = [];
        $results = $query->executeQuery()
            ->fetchAllAssociative();

        foreach ($results as $result) {
            try {
                $items[] = CommentItem::create($result);
            } catch (\Exception $e) {
            }
        }

        return $items;
    }
}
