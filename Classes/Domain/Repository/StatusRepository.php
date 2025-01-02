<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Domain\Repository;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;

class StatusRepository extends Repository
{
    public function __construct(private FrontendInterface $cache)
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

    public function findAll(): array
    {
        $cacheIdentifier = Configuration::CACHE_IDENTIFIER . '--all';
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
        $cacheIdentifier = Configuration::CACHE_IDENTIFIER . '--' . $uid;
        if ($this->cache->has($cacheIdentifier)) {
            return $this->cache->get($cacheIdentifier);
        }

        $query = $this->createQuery();
        $query->matching($query->equals('uid', $uid));
        $result = $query->execute()->getFirst();
        $this->cache->set($cacheIdentifier, $result, $this->collectCacheTags([$result]));
        return $result;
    }

    private function collectCacheTags(array $data): array
    {
        $tags = [];
        foreach ($data as $item) {
            $tags[] = 'tx_ximatypo3contentplanner_domain_model_status_' . $item->getUid();
        }
        return $tags;
    }
}
