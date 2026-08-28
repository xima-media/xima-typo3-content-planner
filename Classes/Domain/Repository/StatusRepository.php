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

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;

use function array_key_exists;
use function is_array;
use function sprintf;

/**
 * StatusRepository.
 *
 * Reads the status table directly via QueryBuilder and hydrates the readonly {@see Status}
 * DTO manually - no Extbase persistence involved.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class StatusRepository
{
    /**
     * Request-level memoization: the repository is a singleton, so this avoids repeated
     * cache backend reads (each a database query with the default backend) when the same
     * status is resolved many times per request, e.g. once per page tree item.
     *
     * @var array<int, Status|null>
     */
    private array $runtimeStatusCache = [];

    /** @var array<int, Status>|null */
    private ?array $runtimeAllCache = null;

    public function __construct(
        private readonly FrontendInterface $cache,
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @return array<int, Status>
     *
     * @throws Exception
     */
    public function findAll(): array
    {
        if (null !== $this->runtimeAllCache) {
            return $this->runtimeAllCache;
        }

        $cacheIdentifier = sprintf('%s--status--all', Configuration::CACHE_IDENTIFIER);
        $cachedResult = $this->cache->get($cacheIdentifier);
        if (is_array($cachedResult)) {
            return $this->runtimeAllCache = $cachedResult;
        }

        $rows = $this->createQueryBuilder()
            ->orderBy('sorting', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative()
        ;

        $result = array_map($this->hydrate(...), $rows);
        $this->cache->set($cacheIdentifier, $result, $this->collectCacheTags($result));

        return $this->runtimeAllCache = $result;
    }

    /**
     * @param int|string|null $uid
     *
     * @throws Exception
     */
    public function findByUid(mixed $uid): ?Status
    {
        if (null === $uid) {
            return null;
        }

        if (array_key_exists($uid, $this->runtimeStatusCache)) {
            return $this->runtimeStatusCache[$uid];
        }

        $cacheIdentifier = sprintf('%s--status--%s', Configuration::CACHE_IDENTIFIER, $uid);
        $cachedResult = $this->cache->get($cacheIdentifier);
        if ($cachedResult instanceof Status) {
            return $this->runtimeStatusCache[$uid] = $cachedResult;
        }

        $queryBuilder = $this->createQueryBuilder();
        $row = $queryBuilder
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter((int) $uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative()
        ;

        if (false === $row) {
            return $this->runtimeStatusCache[$uid] = null;
        }

        $result = $this->hydrate($row);
        $this->cache->set($cacheIdentifier, $result, $this->collectCacheTags([$result]));

        return $this->runtimeStatusCache[$uid] = $result;
    }

    /**
     * @throws Exception
     */
    public function findByTitle(string $title): ?Status
    {
        $queryBuilder = $this->createQueryBuilder();
        $row = $queryBuilder
            ->where($queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter($title)))
            ->executeQuery()
            ->fetchAssociative()
        ;

        return false === $row ? null : $this->hydrate($row);
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_STATUS)
            ->select('uid', 'title', 'icon', 'color')
            ->from(Configuration::TABLE_STATUS)
        ;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Status
    {
        return new Status(
            uid: (int) $row['uid'],
            title: (string) $row['title'],
            icon: (string) $row['icon'],
            color: (string) $row['color'],
        );
    }

    /**
     * @param Status[] $data
     *
     * @return string[]
     */
    private function collectCacheTags(array $data): array
    {
        $tags = [];
        foreach ($data as $item) {
            $tags[] = 'tx_ximatypo3contentplanner_domain_model_status_'.$item->getUid();
        }

        return $tags;
    }
}
