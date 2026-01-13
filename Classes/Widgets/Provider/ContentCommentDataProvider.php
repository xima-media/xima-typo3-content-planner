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

namespace Xima\XimaTypo3ContentPlanner\Widgets\Provider;

use Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\CommentItem;

/**
 * ContentCommentDataProvider.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
readonly class ContentCommentDataProvider implements ListDataProviderInterface
{
    public function __construct(private ConnectionPool $connectionPool) {}

    /**
     * @return CommentItem[]
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function getItems(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_COMMENT);

        $query = $queryBuilder
            ->select(
                'c.uid',
                'c.content',
                'c.author',
                'c.crdate',
                'c.foreign_uid',
                'c.foreign_table',
            )
            ->from(Configuration::TABLE_COMMENT, 'c')
            ->where(
                $queryBuilder->expr()->eq('c.deleted', 0),
                $queryBuilder->expr()->eq('c.resolved_date', 0),
            )
            ->setMaxResults(10)
            ->orderBy('crdate', 'DESC');

        $items = [];
        $results = $query->executeQuery()
            ->fetchAllAssociative();

        foreach ($results as $result) {
            try {
                $items[] = CommentItem::create($result);
            } catch (Exception) {
            }
        }

        return $items;
    }
}
