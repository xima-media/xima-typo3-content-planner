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
        new DateTime();
        $storedTime = new DateTime();
        $storedTime->setTimestamp($timestamp);
        $interval = $now->diff($storedTime);

        if ($interval->y > 0) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf:timeAgo.' . ($interval->y == 1 ? 'year' : 'years')), $interval->y);
        }
        if ($interval->m > 0) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf:timeAgo.' . ($interval->m == 1 ? 'month' : 'months')), $interval->m);
        }
        if ($interval->d > 0) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf:timeAgo.' . ($interval->d == 1 ? 'day' : 'days')), $interval->d);
        }
        if ($interval->h > 0) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf:timeAgo.' . ($interval->h == 1 ? 'hour' : 'hours')), $interval->h);
        }
        if ($interval->i > 0) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf:timeAgo.' . ($interval->i == 1 ? 'minute' : 'minutes')), $interval->i);
        }
        return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf:timeAgo.' . ($interval->s == 1 ? 'second' : 'seconds')), $interval->s);
    }

    public static function checkRecordDiff(array $data, int $actiontype): string|bool
    {
        $old = $data['oldRecord'];
        $new = $data['newRecord'];
        $diff = [];

        foreach ($old as $key => $value) {
            if ($key !== 'l10n_diffsource') {
                $oldValue = $value;
                $newValue = $new[$key];
                if ($oldValue !== $newValue) {
                    $diffValue = self::makeRecordDiffReadable($key, $actiontype, $oldValue, $newValue);
                    if ($diffValue) {
                        $diff[] = $diffValue;
                    }
                }
            }
        }
        return empty($diff) ? false : implode('<br/>', $diff);
    }

    private static function makeRecordDiffReadable(string $field, int $actiontype, string|int|null $old, string|int|null $new): string|bool
    {
        // set value
        if (($old === null || $old === '' || $old === 0) && ($new === null || $new === '' || $new === 0)) {
            return false;
        }
        if ($old === null || $old === '') {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:history.record.' . $actiontype . '.set.' . $field), self::preparePageAttributeValue($field, $new));
        }
        // reset value
        if ($new === null || $new === '') {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:history.record.' . $actiontype . '.unset.' . $field), self::preparePageAttributeValue($field, $old));
        }
        // change value
        if ($old !== $new) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:history.record.' . $actiontype . '.change.' . $field), self::preparePageAttributeValue($field, $old), self::preparePageAttributeValue($field, $new));
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
