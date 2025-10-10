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

use DateTime;
use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Configuration;

use function array_key_exists;
use function sprintf;

/**
 * DiffUtility.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class DiffUtility
{
    public static function timeAgo(int $timestamp): string
    {
        $now = new DateTime();
        $storedTime = (new DateTime())->setTimestamp($timestamp);
        $interval = $now->diff($storedTime);

        if (0 === $interval->s && 0 === $interval->i && 0 === $interval->h && 0 === $interval->d && 0 === $interval->m && 0 === $interval->y) {
            return self::getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:timeAgo.now');
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
                $key = $interval->$unit === 1 ? $label : $label.'s'; // @phpstan-ignore property.dynamicName

                return sprintf(self::getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:timeAgo.'.$key), $interval->$unit); // @phpstan-ignore property.dynamicName
            }
        }

        return sprintf(self::getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang.xlf:timeAgo.seconds'), $interval->s);
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function checkCommendDiff(?array $data, int $actiontype): string|bool
    {
        if (null !== $data && [] !== $data && array_key_exists('newRecord', $data) && array_key_exists('resolved_date', $data['newRecord'])) {
            if (0 === (int) $data['newRecord']['resolved_date']) {
                return self::getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:history.comment.'.$actiontype.'.unresolved');
            }

            return self::getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:history.comment.'.$actiontype.'.resolved');
        }

        return self::getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:history.comment.'.$actiontype);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function checkRecordDiff(array $data, int $actiontype): string|bool
    {
        $diff = [];

        foreach ($data['oldRecord'] as $key => $oldValue) {
            if ('l10n_diffsource' === $key) {
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

        return [] !== $diff ? implode('<br/>', $diff) : false;
    }

    protected static function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    private static function makeRecordDiffReadable(string $field, int $actiontype, string|int|null $old, string|int|null $new): string|bool
    {
        if (!(bool) $old && !(bool) $new) {
            return false;
        }
        if (!(bool) $old) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:history.record.'.$actiontype.'.set.'.$field), self::preparePageAttributeValue($field, $new));
        }
        if (!(bool) $new) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:history.record.'.$actiontype.'.unset.'.$field), self::preparePageAttributeValue($field, $old));
        }
        if ($old !== $new) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:history.record.'.$actiontype.'.change.'.$field), self::preparePageAttributeValue($field, $old), self::preparePageAttributeValue($field, $new));
        }

        return false;
    }

    /**
     * @throws Exception
     */
    private static function preparePageAttributeValue(string $field, string|int $value): string|int|null
    {
        return match ($field) {
            'tx_ximatypo3contentplanner_status' => ContentUtility::getStatus((int) $value)?->getTitle(),
            'tx_ximatypo3contentplanner_assignee' => ContentUtility::getBackendUsernameById((int) $value),
            default => $value,
        };
    }
}
