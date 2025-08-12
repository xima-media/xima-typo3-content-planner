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
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Configuration;

class DiffUtility
{
    public static function timeAgo(int $timestamp): string
    {
        $now = new \DateTime();
        $storedTime = (new \DateTime())->setTimestamp($timestamp);
        $interval = $now->diff($storedTime);

        if ($interval->s === 0 && $interval->i === 0 && $interval->h === 0 && $interval->d === 0 && $interval->m === 0 && $interval->y === 0) {
            return self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf:timeAgo.now');
        }

        $timeUnits = [
            'y' => 'year',
            'm' => 'month',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        ];

        foreach ($timeUnits as $unit => $label) {
            if ($interval->$unit > 0) { // @phpstan-ignore property.dynamicName
                $key = $interval->$unit === 1 ? $label : $label . 's'; // @phpstan-ignore property.dynamicName
                return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf:timeAgo.' . $key), $interval->$unit); // @phpstan-ignore property.dynamicName
            }
        }

        return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf:timeAgo.seconds'), $interval->s);
    }

    /**
    * @param array<string, mixed>|null $data
    */
    public static function checkCommendDiff(?array $data, int $actiontype): string|bool
    {
        if ($data !== null && $data !== [] && array_key_exists('newRecord', $data) && array_key_exists('resolved_date', $data['newRecord'])) {
            if ((int)$data['newRecord']['resolved_date'] === 0) {
                return self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:history.comment.' . $actiontype . '.unresolved');
            }
            return self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:history.comment.' . $actiontype . '.resolved');
        }

        return self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:history.comment.' . $actiontype);
    }

    /**
    * @param array<string, mixed> $data
    */
    public static function checkRecordDiff(array $data, int $actiontype): string|bool
    {
        $diff = [];

        foreach ($data['oldRecord'] as $key => $oldValue) {
            if ($key === 'l10n_diffsource') {
                continue;
            }

            $newValue = $data['newRecord'][$key];
            if ($oldValue !== $newValue) {
                $diffValue = self::makeRecordDiffReadable($key, $actiontype, $oldValue, $newValue);
                if ($diffValue) {
                    $diff[] = $diffValue;
                }
            }
        }

        return $diff !== [] ? implode('<br/>', $diff) : false;
    }

    private static function makeRecordDiffReadable(string $field, int $actiontype, string|int|null $old, string|int|null $new): string|bool
    {
        if (!(bool)$old && !(bool)$new) {
            return false;
        }
        if (!(bool)$old) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:history.record.' . $actiontype . '.set.' . $field), self::preparePageAttributeValue($field, $new));
        }
        if (!(bool)$new) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:history.record.' . $actiontype . '.unset.' . $field), self::preparePageAttributeValue($field, $old));
        }
        if ($old !== $new) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:history.record.' . $actiontype . '.change.' . $field), self::preparePageAttributeValue($field, $old), self::preparePageAttributeValue($field, $new));
        }
        return false;
    }

    /**
    * @throws Exception
    */
    private static function preparePageAttributeValue(string $field, string|int $value): string|int|null
    {
        return match ($field) {
            'tx_ximatypo3contentplanner_status' => ContentUtility::getStatus((int)$value)?->getTitle(),
            'tx_ximatypo3contentplanner_assignee' => ContentUtility::getBackendUsernameById((int)$value),
            default => $value,
        };
    }

    protected static function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
