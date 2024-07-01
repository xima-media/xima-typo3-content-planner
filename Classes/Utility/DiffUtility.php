<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Utility;

use DateTime;
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Configuration;

class DiffUtility {

    public static function timeAgo(int $timestamp): string
    {
        $now = new DateTime();
        new DateTime();
        $storedTime = new DateTime();
        $storedTime->setTimestamp($timestamp);
        $interval = $now->diff($storedTime);

        if ($interval->y > 0) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf:timeAgo.' . ($interval->y == 1 ? 'year' : 'years')), $interval->y);
        } elseif ($interval->m > 0) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf:timeAgo.' . ($interval->m == 1 ? 'month' : 'months')), $interval->m);
        } elseif ($interval->d > 0) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf:timeAgo.' . ($interval->d == 1 ? 'day' : 'days')), $interval->d);
        } elseif ($interval->h > 0) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf:timeAgo.' . ($interval->h == 1 ? 'hour' : 'hours')), $interval->h);
        } elseif ($interval->i > 0) {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf:timeAgo.' . ($interval->i == 1 ? 'minute' : 'minutes')), $interval->i);
        } else {
            return sprintf(self::getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang.xlf:timeAgo.' . ($interval->s == 1 ? 'second' : 'seconds')), $interval->s);
        }
    }

    protected static function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
