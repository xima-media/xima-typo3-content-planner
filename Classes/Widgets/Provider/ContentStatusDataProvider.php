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

namespace Xima\XimaTypo3ContentPlanner\Widgets\Provider;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, StatusRepository};

/**
 * ContentStatusDataProvider.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ContentStatusDataProvider implements ListDataProviderInterface
{
    public function __construct(private readonly StatusRepository $statusRepository, private readonly BackendUserRepository $backendUserRepository) {}

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
     *
     * @throws Exception
     */
    public function getUsers(): array
    {
        return $this->backendUserRepository->findAll();
    }
}
