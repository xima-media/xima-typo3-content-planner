<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets\Provider;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;

class ContentNotesDataProvider implements ListDataProviderInterface
{
    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getItems(): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_ximatypo3contentplanner_note');

        $results = $queryBuilder
            ->select(
                'uid',
                'tstamp',
                'title',
                'content',
                'icon'
            )
            ->from('tx_ximatypo3contentplanner_note')

            ->orderBy('tstamp', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();
        return $results ?: [];
    }
}
