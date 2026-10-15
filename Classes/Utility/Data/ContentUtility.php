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

namespace Xima\XimaTypo3ContentPlanner\Utility\Data;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository, StatusRepository};

/**
 * ContentUtility.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ContentUtility
{
    public static function getStatus(?int $statusId): ?Status
    {
        if (!(bool) $statusId) {
            return null;
        }

        return GeneralUtility::makeInstance(StatusRepository::class)->findByUid($statusId);
    }

    public static function getStatusByTitle(?string $title): ?Status
    {
        if (!(bool) $title) {
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

        if (self::isSystemUser($user)) {
            return self::getSystemLabel();
        }

        if (isset($user['realName']) && '' !== $user['realName']) {
            return $user['realName'].' ('.$user['username'].')';
        }

        return $user['username'];
    }

    /**
     * The real name, falling back to the username: what fits next to an avatar. The CLI user,
     * which means nothing to an editor, reads "System".
     *
     * @param array<string, mixed> $user
     */
    public static function generateShortDisplayName(array $user): string
    {
        if (self::isSystemUser($user)) {
            return self::getSystemLabel();
        }

        return (string) (($user['realName'] ?? '') ?: ($user['username'] ?? ''));
    }

    /**
     * @return array<string, mixed>|bool
     *
     * @Deprecated
     *
     * @throws Exception
     */
    public static function getComment(int $id): array|bool
    {
        if (!(bool) $id) {
            return false;
        }

        return GeneralUtility::makeInstance(CommentRepository::class)->findByUid($id);
    }

    /**
     * @return array<string, mixed>|bool
     *
     * @Deprecated
     *
     * @throws Exception
     */
    public static function getBackendUserById(?int $userId): array|bool
    {
        if (!(bool) $userId) {
            return false;
        }

        return GeneralUtility::makeInstance(BackendUserRepository::class)->findByUid($userId);
    }

    /**
     * @Deprecated
     *
     * @throws Exception
     */
    public static function getBackendUsernameById(?int $userId): string
    {
        if (!(bool) $userId) {
            return '';
        }

        return GeneralUtility::makeInstance(BackendUserRepository::class)->getUsernameByUid($userId);
    }

    /**
     * @return array<string, mixed>|null
     *
     * @Deprecated
     */
    public static function getExtensionRecord(?string $table, ?int $uid): ?array
    {
        if (!(bool) $table && !(bool) $uid) {
            return null;
        }

        return BackendUtility::getRecord($table, $uid);
    }

    /**
     * @param array<string, mixed> $user
     */
    private static function isSystemUser(array $user): bool
    {
        return '_cli_' === ($user['username'] ?? null);
    }

    private static function getSystemLabel(): string
    {
        return GeneralUtility::makeInstance(BackendUserRepository::class)->getSystemLabel();
    }
}
