<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\{QueryInterface, Repository};
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;

use function is_array;
use function sprintf;

/**
 * StatusRepository.
 *
 * @extends Repository<Status>
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class StatusRepository extends Repository
{
    protected $defaultOrderings = [
        'sorting' => QueryInterface::ORDER_ASCENDING,
    ];

    public function __construct(private readonly FrontendInterface $cache)
    {
        parent::__construct();
    }

    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * @return array<int, Status>
     *
     * @phpstan-ignore-next-line property.phpDocType
     */
    public function findAll(): array
    {
        $cacheIdentifier = sprintf('%s--status--all', Configuration::CACHE_IDENTIFIER);
        $cachedResult = $this->cache->get($cacheIdentifier);
        if (is_array($cachedResult)) {
            return $cachedResult;
        }

        $query = $this->createQuery();
        $result = $query->execute()->toArray();
        $this->cache->set($cacheIdentifier, $result, $this->collectCacheTags($result));

        return $result;
    }

    public function findByUid($uid): ?Status
    {
        $cacheIdentifier = sprintf('%s--status--%s', Configuration::CACHE_IDENTIFIER, $uid);
        $cachedResult = $this->cache->get($cacheIdentifier);
        if ($cachedResult instanceof Status) {
            return $cachedResult;
        }

        $query = $this->createQuery();
        $query->matching($query->equals('uid', $uid));
        /** @var Status|null $result */
        $result = $query->execute()->getFirst();

        if (null === $result) {
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
