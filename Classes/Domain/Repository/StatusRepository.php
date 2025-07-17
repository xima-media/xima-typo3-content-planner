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

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;

/**
* @extends Repository<Status>
*/
class StatusRepository extends Repository
{
    public function __construct(private readonly FrontendInterface $cache)
    {
        parent::__construct();
    }

    protected $defaultOrderings = [
        'sorting' => QueryInterface::ORDER_ASCENDING,
    ];

    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
    * @return array<int, Status>
    * @phpstan-ignore-next-line property.phpDocType
    */
    public function findAll(): array
    {
        $cacheIdentifier = sprintf('%s--status--all', Configuration::CACHE_IDENTIFIER);
        if ($this->cache->has($cacheIdentifier)) {
            return $this->cache->get($cacheIdentifier);
        }

        $query = $this->createQuery();
        $result = $query->execute()->toArray();
        $this->cache->set($cacheIdentifier, $result, $this->collectCacheTags($result));
        return $result;
    }

    public function findByUid($uid): ?Status
    {
        $cacheIdentifier = sprintf('%s--status--%s', Configuration::CACHE_IDENTIFIER, $uid);
        if ($this->cache->has($cacheIdentifier)) {
            return $this->cache->get($cacheIdentifier);
        }

        $query = $this->createQuery();
        $query->matching($query->equals('uid', $uid));
        /** @var Status|null $result */
        $result = $query->execute()->getFirst();

        if ($result === null) {
            return null;
        }
        $this->cache->set($cacheIdentifier, $result, $this->collectCacheTags([$result]));
        return $result;
    }

    public function findByTitle(string $title): ?Status
    {
        $query = $this->createQuery();
        $query->matching($query->equals('title', $title));
        /** @var Status|null $result */
        $result = $query->execute()->getFirst();
        return $result;
    }

    /**
    * @param Status[] $data
    * @return string[]
    */
    private function collectCacheTags(array $data): array
    {
        $tags = [];
        foreach ($data as $item) {
            $tags[] = 'tx_ximatypo3contentplanner_domain_model_status_' . $item->getUid();
        }
        return $tags;
    }
}
