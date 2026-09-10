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

namespace Xima\XimaTypo3ContentPlanner\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;

/**
 * StatusTitleViewHelper.
 *
 * CP-14 (#318): resolves a status title from its uid, so templates can pair a status
 * colour with a real text label (e.g. for `title`/`aria-label` on controls that only
 * carry the colour via a `data-color` attribute, such as the content element hint dots
 * in HeaderInfo.html).
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class StatusTitleViewHelper extends AbstractViewHelper
{
    public function __construct(private readonly StatusRepository $statusRepository) {}

    public function initializeArguments(): void
    {
        $this->registerArgument(
            'statusId',
            'integer',
            '',
            true,
        );
    }

    public function render(): string
    {
        $status = $this->statusRepository->findByUid($this->arguments['statusId']);

        if (!$status instanceof Status) {
            return '';
        }

        return $status->getTitle();
    }
}
