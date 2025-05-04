<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Utility;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\BackendUserRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\CommentRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\SysFileMetadataRepository;

class ContentUtility
{
    public static function getStatus(?int $statusId): ?Status
    {
        if (!$statusId) {
            return null;
        }
        $statusRepository = GeneralUtility::makeInstance(StatusRepository::class);
        return $statusRepository->findByUid($statusId);
    }

    public static function getStatusByTitle(?string $title): ?Status
    {
        if (!$title) {
            return null;
        }
        $statusRepository = GeneralUtility::makeInstance(StatusRepository::class);
        return $statusRepository->findByTitle($title);
    }

    public static function getPage(int $pageId): array|bool
    {
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        return $pageRepository->getPage($pageId);
    }

    /**
    * @Deprecated
    */
    public static function getComment(int $id): array|bool
    {
        if (!$id) {
            return false;
        }

        $commentRepository = GeneralUtility::makeInstance(CommentRepository::class);
        return $commentRepository->findByUid($id);
    }

    /**
    * @Deprecated
    */
    public static function getBackendUserById(?int $userId): array|bool
    {
        if (!$userId) {
            return false;
        }

        $backendUserRepository = GeneralUtility::makeInstance(BackendUserRepository::class);
        return $backendUserRepository->findByUid($userId);
    }

    /**
    * @Deprecated
    */
    public static function getBackendUsernameById(?int $userId): string
    {
        if (!$userId) {
            return '';
        }

        $backendUserRepository = GeneralUtility::makeInstance(BackendUserRepository::class);
        return $backendUserRepository->getUsernameByUid($userId);
    }

    /**
    * @Deprecated
    */
    public static function getExtensionRecord(?string $table, ?int $uid): array|null
    {
        if (!$table && !$uid) {
            return null;
        }
        if ($table === 'sys_file_metadata') {
            $sysFileMetadataRepository = GeneralUtility::makeInstance(SysFileMetadataRepository::class);
            return $sysFileMetadataRepository->findByUid($uid);
        }
        return BackendUtility::getRecord($table, $uid);
    }
}
