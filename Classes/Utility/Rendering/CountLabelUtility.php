<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Utility\Rendering;

use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Configuration;

use function sprintf;

/**
 * CountLabelUtility.
 *
 * The text names of the comment and to-do counts that every status surface shares (doc header
 * group, content element strip, banner, docked bar), so a count always carries a name as text
 * (docs/design/rules.md, "Accessibility").
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CountLabelUtility
{
    private const LLL = 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/';

    /**
     * "Comments" while there are none, otherwise "1 comment" or "3 comments".
     */
    public static function comments(int $count): string
    {
        $languageService = self::getLanguageService();

        return match (true) {
            $count <= 0 => $languageService->sL(self::LLL.'locallang.xlf:button.modal.header.comments'),
            1 === $count => sprintf($languageService->sL(self::LLL.'locallang_be.xlf:comments.count.one'), $count),
            default => sprintf($languageService->sL(self::LLL.'locallang_be.xlf:comments.count.other'), $count),
        };
    }

    /**
     * "0 of 2 to-dos done".
     */
    public static function todos(int $resolved, int $total): string
    {
        return sprintf(self::getLanguageService()->sL(self::LLL.'locallang.xlf:header.todos.name'), $resolved, $total);
    }

    private static function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
