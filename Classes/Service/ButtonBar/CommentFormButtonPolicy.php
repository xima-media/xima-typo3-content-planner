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

namespace Xima\XimaTypo3ContentPlanner\Service\ButtonBar;

use TYPO3\CMS\Backend\Template\Components\Buttons\{InputButton, LinkButton};
use TYPO3\CMS\Core\Localization\LanguageService;
use Xima\XimaTypo3ContentPlanner\Configuration;

use function is_array;
use function str_contains;

/**
 * CommentFormButtonPolicy.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class CommentFormButtonPolicy
{
    /**
     * Class core puts on the close button, which it registers as a link button. The form
     * engine binds the click handler that navigates to the returnUrl.
     */
    private const CLOSE_BUTTON_CLASS = 't3js-editform-close';

    /**
     * @param array<string, array<int, mixed>> $buttons
     *
     * @return array<string, array<int, mixed>>
     */
    public function apply(array $buttons, bool $keepCloseButton): array
    {
        $result = [];

        foreach ($buttons as $position => $group) {
            if ('right' === $position) {
                continue;
            }

            $kept = $this->filterGroup($group, $keepCloseButton);
            if ([] !== $kept) {
                $result[$position] = $kept;
            }
        }

        return $result;
    }

    /**
     * @param array<int, mixed> $group
     *
     * @return array<int, mixed>
     */
    private function filterGroup(array $group, bool $keepCloseButton): array
    {
        $kept = [];

        foreach ($group as $button) {
            $candidate = is_array($button) ? ($button[0] ?? null) : null;

            if ($this->isSaveButton($candidate)) {
                $this->labelSaveButton($candidate, $keepCloseButton);
                $kept[] = $button;
            } elseif ($keepCloseButton && $this->isCloseButton($candidate)) {
                $kept[] = $button;
            }
        }

        return $kept;
    }

    private function isSaveButton(mixed $button): bool
    {
        return $button instanceof InputButton && str_contains($button->getName(), '_save');
    }

    private function isCloseButton(mixed $button): bool
    {
        return $button instanceof LinkButton && str_contains($button->getClasses(), self::CLOSE_BUTTON_CLASS);
    }

    private function labelSaveButton(mixed $button, bool $keepCloseButton): void
    {
        if ($keepCloseButton || !$button instanceof InputButton) {
            return;
        }

        $button->setTitle($this->getLanguageService()->sL(
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:save_and_close',
        ));
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
