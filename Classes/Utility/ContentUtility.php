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

namespace Xima\XimaTypo3ContentPlanner\Utility;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;

/**
 * ContentUtility.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class ContentUtility
{
    public static function getStatus(?int $statusId): ?Status
    {
        if (!(bool)$statusId) {
            return null;
        }
        return GeneralUtility::makeInstance(StatusRepository::class)->findByUid($statusId);
    }

    public static function getStatusByTitle(?string $title): ?Status
    {
        if (!(bool)$title) {
            return null;
        }
        return GeneralUtility::makeInstance(StatusRepository::class)->findByTitle($title);
    }

    /**
    * @return array<string, mixed>|bool
    */
    public static function getPage(int $pageId): array|bool
    {
        return GeneralUtility::makeInstance(PageRepository::class)->getPage($pageId);
    }

    /**
    * @param array<string, mixed> $user
    */
    public static function generateDisplayName(array $user): string
    {
        if (!isset($user['username'])) {
            return '';
        }

        if (isset($user['realname']) && $user['realname'] !== '') {
            return $user['realname'] . ' (' . $user['username'] . ')';
        }

        return $user['username'];
    }

    /**
    * @return array<string, mixed>|bool
    * @Deprecated
    */
    public static function getComment(int $id): array|bool
    {
        if (!(bool)$id) {
            return false;
        }

        return GeneralUtility::makeInstance(CommentRepository::class)->findByUid($id);
    }

    /**
    * @return array<string, mixed>|bool
    * @Deprecated
    * @throws Exception
    */
    public static function getBackendUserById(?int $userId): array|bool
    {
        if (!(bool)$userId) {
            return false;
        }

        return GeneralUtility::makeInstance(BackendUserRepository::class)->findByUid($userId);
    }

    /**
    * @Deprecated
    * @throws Exception
    */
    public static function getBackendUsernameById(?int $userId): string
    {
        if (!(bool)$userId) {
            return '';
        }

        return GeneralUtility::makeInstance(BackendUserRepository::class)->getUsernameByUid($userId);
    }

    /**
    * @return array<string, mixed>|null
    * @Deprecated
    */
    public static function getExtensionRecord(?string $table, ?int $uid): array|null
    {
        if (!(bool)$table && !(bool)$uid) {
            return null;
        }
        return BackendUtility::getRecord($table, $uid);
    }
}
