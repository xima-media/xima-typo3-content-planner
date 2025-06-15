<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Utility;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;

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

    public static function getPage(int $pageId): array|bool
    {
        return GeneralUtility::makeInstance(PageRepository::class)->getPage($pageId);
    }

    public static function generateDisplayName(array $user): string
    {
        if (!isset($user['username']) || !isset($user['realname'])) {
            return '';
        }

        if ($user['realname'] !== '') {
            return $user['realname'] . ' (' . $user['username'] . ')';
        }

        return $user['username'];
    }

    /**
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
