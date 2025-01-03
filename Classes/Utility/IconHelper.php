<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Utility;

use TYPO3\CMS\Backend\Backend\Avatar\Avatar;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;

class IconHelper
{
    public static function getIconByIdentifier(string $identifier, string $size = Icon::SIZE_SMALL): string
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $icon = $iconFactory->getIcon($identifier, $size);
        return $icon->render();
    }

    public static function getIconByStatusUid(int $uid, bool $render = false): string
    {
        $status = ContentUtility::getStatus($uid);
        return self::getIconByStatus($status, $render);
    }

    public static function getIconByStatus(?Status $status, bool $render = false, string $size = Icon::SIZE_SMALL): string
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $icon = $iconFactory->getIcon($status ? $status->getColoredIcon() : 'flag-gray', $size);
        return $render ? $icon->render() : $icon->getIdentifier();
    }

    public static function getIconByRecord(string $table, array|bool $record, bool $render = false, string $size = Icon::SIZE_SMALL): string
    {
        if (!$record) {
            return '';
        }
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $icon = $iconFactory->getIconForRecord($table, $record, $size);
        return $render ? $icon->render() : $icon->getIdentifier();
    }

    public static function getAvatarByUserId(int $userId, int $size = 15): string
    {
        $user = ContentUtility::getBackendUserById($userId);
        return self::getAvatarByUser($user, $size);
    }

    public static function getAvatarByUser(array|bool $user, int $size = 15): string
    {
        if (!$user) {
            return '';
        }
        $avatar = GeneralUtility::makeInstance(Avatar::class);
        return $avatar->render($user, $size, true);
    }
}
