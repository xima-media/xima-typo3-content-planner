<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets\Provider;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\StatusItem;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;

class ContentStatusDataProvider implements ListDataProviderInterface
{
    public function __construct(protected readonly StatusRepository $statusRepository)
    {
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getItems(): array
    {
        return $this->getItemsByDemand();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getItemsByDemand(?int $status = null, ?int $assignee = null, int $maxResults = 20): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');

        $additionalWhere = '';
        $additionalParams = [
            'limit' => $maxResults,
        ];
        if ($status) {
            $additionalWhere .= ' AND tx_ximatypo3contentplanner_status = :status';
            $additionalParams['status'] = $status;
        }
        if ($assignee) {
            $additionalWhere .= ' AND tx_ximatypo3contentplanner_assignee = :assignee';
            $additionalParams['assignee'] = $assignee;
        }
        $sqlArray = [];
        foreach (ExtensionUtility::getRecordTables() as $table) {
            $sqlArray[] = '(SELECT "' . $table . '" as tablename, uid, title, tstamp, tx_ximatypo3contentplanner_status, tx_ximatypo3contentplanner_assignee, tx_ximatypo3contentplanner_comments FROM ' . $table . ' WHERE tx_ximatypo3contentplanner_status IS NOT NULL' . $additionalWhere . ')';
        }
        $sql = implode(' UNION ', $sqlArray) . ' ORDER BY tstamp DESC LIMIT :limit';

        $statement = $queryBuilder->getConnection()->executeQuery($sql, $additionalParams);
        $results = $statement->fetchAllAssociative();

        foreach ($results as $result) {
            try {
                $items[] = StatusItem::create($result);
            } catch (\Exception $e) {
            }
        }

        return $items;
    }

    public function getStatus(): QueryResultInterface|array
    {
        return $this->statusRepository->findAll();
    }

    public function getUsers(): array
    {
        return ContentUtility::getBackendUsers();
    }
}
