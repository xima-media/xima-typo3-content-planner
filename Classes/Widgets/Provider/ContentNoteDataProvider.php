<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets\Provider;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ContentNoteDataProvider
{
    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getItem(): array|bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_ximatypo3contentplanner_note');

        return $queryBuilder
            ->select(
                'uid',
                'title',
                'content'
            )
            ->from('tx_ximatypo3contentplanner_note')

            ->orderBy('tstamp', 'DESC')
            ->executeQuery()
            ->fetchAssociative();
    }
}
