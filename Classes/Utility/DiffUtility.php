<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Utility;

use DateTime;
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Configuration;

class DiffUtility
{
    public static function timeAgo(int $timestamp): string
    {
        $now = new DateTime();
        $storedTime = (new DateTime())->setTimestamp($timestamp);
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
            if ($interval->$unit > 0) {
                $key = $interval->$unit == 1 ? $label : $label . 's';
                return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf:timeAgo.' . $key), $interval->$unit);
            }
        }

        return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf:timeAgo.seconds'), $interval->s);
    }

    public static function checkCommendDiff(?array $data, int $actiontype): string|bool
    {
        if ($data && array_key_exists('newRecord', $data) && array_key_exists('resolved_date', $data['newRecord'])) {
            if ((int)$data['newRecord']['resolved_date'] === 0) {
                return self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:history.comment.' . $actiontype . '.unresolved');
            }
            return self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:history.comment.' . $actiontype . '.resolved');
        }

        return self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:history.comment.' . $actiontype);
    }

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

        return $diff ? implode('<br/>', $diff) : false;
    }

    private static function makeRecordDiffReadable(string $field, int $actiontype, string|int|null $old, string|int|null $new): string|bool
    {
        if (empty($old) && empty($new)) {
            return false;
        }
        if (empty($old)) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:history.record.' . $actiontype . '.set.' . $field), self::preparePageAttributeValue($field, $new));
        }
        if (empty($new)) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:history.record.' . $actiontype . '.unset.' . $field), self::preparePageAttributeValue($field, $old));
        }
        if ($old !== $new) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:history.record.' . $actiontype . '.change.' . $field), self::preparePageAttributeValue($field, $old), self::preparePageAttributeValue($field, $new));
        }
        return false;
    }

    private static function preparePageAttributeValue(string $field, string|int $value): string|int|null
    {
        switch ($field) {
            case 'tx_ximatypo3contentplanner_status':
                return ContentUtility::getStatus((int)$value)?->getTitle();
            case 'tx_ximatypo3contentplanner_assignee':
                return ContentUtility::getBackendUsernameById((int)$value);
        }
        return $value;
    }

    protected static function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
