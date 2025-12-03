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

namespace Xima\XimaTypo3ContentPlanner\Utility;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Backend\Backend\Avatar\Avatar;
use TYPO3\CMS\Core\Imaging\{IconFactory, IconSize};
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;

/**
 * IconHelper.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
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

    public static function getDefaultIconSize(): string|IconSize
    {
        return IconSize::SMALL;
    }
}
