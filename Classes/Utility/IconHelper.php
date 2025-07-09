<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Utility;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Backend\Backend\Avatar\Avatar;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;

class IconHelper
{
    public static function getIconByIdentifier(string $identifier): string
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        return $iconFactory->getIcon($identifier, self::getDefaultIconSize())->render();
    }

    public static function getIconByStatusUid(int $uid, bool $render = false): string
    {
        $status = ContentUtility::getStatus($uid);
        return self::getIconByStatus($status, $render);
    }

    public static function getIconByStatus(?Status $status, bool $render = false): string
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $icon = $iconFactory->getIcon($status instanceof Status ? $status->getColoredIcon() : 'flag-gray', self::getDefaultIconSize());
        return $render ? $icon->render() : $icon->getIdentifier();
    }

    /**
    * @param array<string, mixed>|bool $record
    */
    public static function getIconByRecord(string $table, array|bool $record, bool $render = false): string
    {
        if (!$record) {
            return '';
        }
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $icon = $iconFactory->getIconForRecord($table, $record, self::getDefaultIconSize());
        return $render ? $icon->render() : $icon->getIdentifier();
    }

    /**
    * @throws Exception
    */
    public static function getAvatarByUserId(int $userId, int $size = 15): string
    {
        $user = ContentUtility::getBackendUserById($userId);
        return self::getAvatarByUser($user, $size);
    }

    /**
    * @param array<string, mixed>|bool $user
    */
    public static function getAvatarByUser(array|bool $user, int $size = 15): string
    {
        if (!$user) {
            return '';
        }
        return GeneralUtility::makeInstance(Avatar::class)->render($user, $size, true);
    }

    public static function getDefaultIconSize(): string|\TYPO3\CMS\Core\Imaging\IconSize
    {
        $typo3Version = GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion();

        if ($typo3Version >= 13) {
            return \TYPO3\CMS\Core\Imaging\IconSize::SMALL;
        }
        return \TYPO3\CMS\Core\Imaging\Icon::SIZE_SMALL; // @phpstan-ignore classConstant.deprecated
    }
}
