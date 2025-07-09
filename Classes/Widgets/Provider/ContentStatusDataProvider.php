<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Widgets\Provider;

use Doctrine\DBAL\Exception;
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
    * @return array<empty, empty>
    */
    public function getItems(): array
    {
        return [];
    }

    /**
    * @return QueryResultInterface<int, \Xima\XimaTypo3ContentPlanner\Domain\Model\Status>|array<int, \Xima\XimaTypo3ContentPlanner\Domain\Model\Status>
    */
    public function getStatus(): QueryResultInterface|array
    {
        return $this->statusRepository->findAll();
    }

    /**
    * @return array<int, array<string, mixed>>
    * @throws Exception
    */
    public function getUsers(): array
    {
        return $this->backendUserRepository->findAll();
    }
}
