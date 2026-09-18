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

namespace Xima\XimaTypo3ContentPlanner\Service\Header\Buttons;

use Stringable;
use TYPO3\CMS\Backend\Template\Components\Buttons\ButtonInterface;

/**
 * RawMarkupButton.
 *
 * Wraps an already-rendered markup fragment (e.g. a Fluid partial) as a doc header
 * ButtonBar entry. Lets {@see \Xima\XimaTypo3ContentPlanner\Service\Header\ChipTrioButtonBuilder}
 * reuse the existing {@see \Xima\XimaTypo3ContentPlanner\Service\WatcherPresentationService}
 * watch-toggle partial verbatim (same markup, data attributes and JS wiring as the banner)
 * instead of re-building an equivalent button by hand.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class RawMarkupButton implements ButtonInterface, Stringable
{
    public function __construct(private string $markup) {}

    public function __toString(): string
    {
        return $this->render();
    }

    public function isValid(): bool
    {
        return '' !== trim($this->markup);
    }

    public function getType(): string
    {
        return self::class;
    }

    public function render(): string
    {
        return $this->markup;
    }
}
