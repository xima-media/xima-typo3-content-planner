<?php

declare(strict_types=1);

namespace Xima\XimaContentPlanner\Widgets\Provider;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentNoteDataProvider
{
    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getItem(): array|bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_ximacontentplanner_note');

        return $queryBuilder
            ->select(
                'uid',
                'title',
                'content'
            )
            ->from('tx_ximacontentplanner_note')

            ->orderBy('tstamp', 'DESC')
            ->executeQuery()
            ->fetchAssociative();
    }
}
