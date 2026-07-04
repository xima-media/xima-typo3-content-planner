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

use Doctrine\DBAL\{ArrayParameterType, Exception};
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\HistoryItem;

use function array_merge;
use function array_slice;

/**
 * ContentUpdateDataProvider.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ContentUpdateDataProvider implements ListDataProviderInterface
{
    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @return HistoryItem[]
     *
     * @throws Exception
     */
    public function getItems(): array
    {
        return $this->fetchUpdateData(maxItems: 15);
    }

    /**
     * @return HistoryItem[]
     *
     * @throws Exception
     *
     * @phpstan-ignore-next-line typePerfect.narrowPublicClassMethodParamType
     */
    public function fetchUpdateData(?int $beUser = null, ?int $tstamp = null, ?int $maxItems = null): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_history');

        $recordTables = array_merge(
            ['pages'],
            (array) ($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]['registerAdditionalRecordTables'] ?? []),
        );
        $trackedTables = array_merge($recordTables, [Configuration::TABLE_COMMENT]);

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
            ->leftJoin('h', 'be_users', 'b', 'h.userid = b.uid')
            ->where(
                // Leading, index-friendly restriction on the indexed tablename column.
                $queryBuilder->expr()->in(
                    'h.tablename',
                    $queryBuilder->createNamedParameter($trackedTables, ArrayParameterType::STRING),
                ),
                // Comment history always qualifies; record history only when a content planner field changed.
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq(
                        'h.tablename',
                        $queryBuilder->createNamedParameter(Configuration::TABLE_COMMENT),
                    ),
                    $queryBuilder->expr()->like(
                        'h.history_data',
                        $queryBuilder->createNamedParameter('%tx_ximatypo3contentplanner%'),
                    ),
                ),
            )
            ->orderBy('h.tstamp', 'DESC');

        if ((bool) $maxItems) {
            $query->setMaxResults($maxItems * 2);
        }

        if ((bool) $tstamp) {
            $query->andWhere(
                $queryBuilder->expr()->gt('h.tstamp', $queryBuilder->createNamedParameter($tstamp, Connection::PARAM_INT)),
            );
        }

        $items = [];
        $results = $query->executeQuery()
            ->fetchAllAssociative();

        foreach ($results as $result) {
            try {
                $items[] = HistoryItem::create($result);
            } catch (\Exception) {
            }
        }

        foreach ($items as $key => $item) {
            if ('' === $item->getHistoryData()) {
                unset($items[$key]);
            }
        }

        if ((bool) $maxItems) {
            $items = array_slice($items, 0, $maxItems);
        }

        return $items;
    }
}
