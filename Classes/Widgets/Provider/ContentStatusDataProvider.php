<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets\Provider;

use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;

class ContentStatusDataProvider implements ListDataProviderInterface
{
    public function __construct(private readonly StatusRepository $statusRepository, private readonly BackendUserRepository $backendUserRepository)
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
        return $this->backendUserRepository->findAll();
    }
}
