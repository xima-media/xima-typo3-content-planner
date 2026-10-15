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

namespace Xima\XimaTypo3ContentPlanner\Service\Header;

use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder\SelectionUriBuilder;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

/**
 * StatusOptionsBuilder.
 *
 * The statuses a record can be switched to from the status of the banner or the content element
 * strip: every status the user may set, the current one marked, and the link that removes the
 * status. The links are the ones the doc header dropdown uses ({@see SelectionUriBuilder}), so
 * both change a status the same way.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class StatusOptionsBuilder
{
    public function __construct(
        private readonly StatusRepository $statusRepository,
        private readonly SelectionUriBuilder $selectionUriBuilder,
    ) {}

    /**
     * @return array{statuses: list<array{uid: int, title: string, icon: string, current: bool, url: string}>, resetUrl: string|null}
     *
     * @throws RouteNotFoundException
     */
    public function build(string $table, int $uid, int $currentStatusUid): array
    {
        if (!PermissionUtility::canChangeStatus()) {
            return ['statuses' => [], 'resetUrl' => null];
        }

        $statuses = [];
        foreach ($this->statusRepository->findAll() as $status) {
            if (!PermissionUtility::isStatusAllowedForUser($status->getUid())) {
                continue;
            }

            $statuses[] = [
                'uid' => $status->getUid(),
                'title' => $status->getTitle(),
                'icon' => $status->getColoredIcon(),
                'current' => $status->getUid() === $currentStatusUid,
                'url' => (string) $this->selectionUriBuilder->buildUriForStatusChange($table, $uid, $status),
            ];
        }

        return [
            'statuses' => $statuses,
            'resetUrl' => $currentStatusUid > 0 && PermissionUtility::canUnsetStatus()
                ? (string) $this->selectionUriBuilder->buildUriForStatusChange($table, $uid, null)
                : null,
        ];
    }
}
