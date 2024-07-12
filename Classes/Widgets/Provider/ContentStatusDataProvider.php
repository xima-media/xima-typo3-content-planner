<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets\Provider;

use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ContentUtility;

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
        return [];
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
