<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "xima_typo3_content_planner".
 *
 * Copyright (C) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Xima\XimaTypo3ContentPlanner\Widgets\Provider;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\HistoryItem;

class ContentUpdateDataProvider implements ListDataProviderInterface
{
    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
    * @return HistoryItem[]
    * @throws Exception
    */
    public function getItems(): array
    {
        return $this->fetchUpdateData(maxItems: 15);
    }

    /**
    * @return HistoryItem[]
    * @throws \Doctrine\DBAL\Exception
    */
    public function fetchUpdateData(?int $beUser = null, ?int $tstamp = null, ?int $maxItems = null): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_history');

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

        if ((bool)$maxItems) {
            $query->setMaxResults($maxItems * 2);
        }

        if ((bool)$tstamp) {
            $query->andWhere('h.tstamp > :tstamp')
                ->setParameter('tstamp', $tstamp);
        }

        $items = [];
        $results = $query->executeQuery()
            ->fetchAllAssociative();

        foreach ($results as $result) {
            try {
                $items[] = HistoryItem::create($result);
            } catch (\Exception $e) {
            }
        }

        foreach ($items as $key => $item) {
            if ($item->getHistoryData() === '') {
                unset($items[$key]);
            }
        }

        if ((bool)$maxItems) {
            $items = array_slice($items, 0, $maxItems);
        }

        return $items;
    }
}
