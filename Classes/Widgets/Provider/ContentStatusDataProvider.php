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

namespace Xima\XimaTypo3ContentPlanner\Widgets\Provider;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;

/**
 * ContentStatusDataProvider.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
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
    * @throws Exception
    */
    public function getUsers(): array
    {
        return $this->backendUserRepository->findAll();
    }
}
