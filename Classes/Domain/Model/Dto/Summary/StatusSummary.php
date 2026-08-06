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

namespace Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\Summary;

use Xima\XimaTypo3ContentPlanner\Configuration\Colors;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;

use function in_array;

/**
 * StatusSummary.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class StatusSummary
{
    public function __construct(
        public int $uid,
        public string $title,
        public string $colorName,
        public ?string $colorHex,
        public string $iconIdentifier,
    ) {}

    public static function fromStatus(Status $status): self
    {
        $colorName = $status->getColor();

        return new self(
            uid: (int) $status->getUid(),
            title: $status->getTitle(),
            colorName: $colorName,
            // Colors::getHex() throws for anything outside its palette, and a legacy or
            // hand-edited status record can carry an empty colour. One such record must
            // not take down a whole batch response.
            colorHex: in_array($colorName, Colors::STATUS_COLORS, true) ? Colors::getHex($colorName) : null,
            // The identifier, not rendered markup — rendering is the consumer's job.
            iconIdentifier: $status->getColoredIcon(),
        );
    }

    /**
     * @return array{uid: int, title: string, colorName: string, colorHex: string|null, iconIdentifier: string}
     */
    public function toArray(): array
    {
        return [
            'uid' => $this->uid,
            'title' => $this->title,
            'colorName' => $this->colorName,
            'colorHex' => $this->colorHex,
            'iconIdentifier' => $this->iconIdentifier,
        ];
    }
}
