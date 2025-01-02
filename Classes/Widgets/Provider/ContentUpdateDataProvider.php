<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets\Provider;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\HistoryItem;

class ContentUpdateDataProvider implements ListDataProviderInterface
{
    /**
    * @throws \Doctrine\DBAL\Exception
    */
    public function getItems(): array
    {
        return $this->fetchUpdateData(maxItems: 15);
    }

    public function fetchUpdateData(?int $beUser = null, ?int $tstamp = null, ?int $maxItems = null, bool $cliContext = false): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_history');

        $tablesArray = array_merge(['pages'], $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables']);
        // surround every table with quotes
        $tables = implode(',', array_map(function ($table) {
            return '"' . $table . '"';
        }, $tablesArray));
        $query = $queryBuilder
            ->select(
                'h.uid',
                'h.tstamp as tstamp',
                'h.recuid as recuid',
                'h.userid as userid',
                'h.actiontype as actiontype',
                'h.tablename as tablename',
                'h.history_data as history_data',
                'b.username as username',
                'b.realName as realName',
            )
            ->from('sys_history', 'h')
            ->leftJoin('h', 'pages', 'p', 'h.recuid = p.uid')
            ->leftJoin('h', 'be_users', 'b', 'h.userid = b.uid')
            ->andWhere('(h.history_data LIKE "%tx_ximatypo3contentplanner%" AND h.tablename IN (' . $tables . ')) OR (h.tablename = "tx_ximatypo3contentplanner_comment")')
            ->orderBy('h.tstamp', 'DESC');

        if ($maxItems) {
            $query->setMaxResults($maxItems*2);
        }

        if ($tstamp) {
            $query->andWhere('h.tstamp > :tstamp')
                ->setParameter('tstamp', $tstamp);
        }

        $items = [];
        $results = $query->executeQuery()
            ->fetchAllAssociative();

        foreach ($results as $result) {
            try {
                $items[] = HistoryItem::create($result, $cliContext);
            } catch (\Exception $e) {
            }
        }

        foreach ($items as $key => $item) {
            if ($item->getHistoryData() === '') {
                unset($items[$key]);
            }
        }

        if ($maxItems) {
            $items = array_slice($items, 0, $maxItems);
        }

        return $items;
    }
}
