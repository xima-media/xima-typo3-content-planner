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

namespace Xima\XimaTypo3ContentPlanner\Manager;

use Doctrine\DBAL\Exception;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

/**
 * CommentFirstFlowManager.
 *
 * CP-27 (#326): decides what the comment composer on a status-less record should offer -
 * nothing (record already has a status, or the user cannot change status at all), the
 * one-click flow (a status is marked "is_default" and the user is allowed to set it), or the
 * inline picker fallback (no usable default, but at least one status the user may set).
 * Shared between RecordController (building the composer's template context) and
 * CommentEditorController (validating a submitted statusUid) to keep the decision in one
 * place instead of duplicated across both controllers.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class CommentFirstFlowManager
{
    public function __construct(
        private StatusRepository $statusRepository,
    ) {}

    /**
     * @param array<string, mixed> $record
     *
     * @return array{active: bool, mode?: string, defaultStatus?: array<string, mixed>, statuses?: array<int, array<string, mixed>>}
     *
     * @throws Exception
     */
    public function buildContext(array $record): array
    {
        if (PlannerUtility::hasStatus($record) || !PermissionUtility::canChangeStatus()) {
            return ['active' => false];
        }

        $default = $this->statusRepository->findDefault();
        if ($default instanceof Status && PermissionUtility::canChangeStatus($default->getUid())) {
            return [
                'active' => true,
                'mode' => 'oneClick',
                'defaultStatus' => $this->toItemArray($default),
            ];
        }

        $allowedStatuses = array_values(array_filter(
            $this->statusRepository->findAll(),
            static fn (Status $status): bool => PermissionUtility::canChangeStatus($status->getUid()),
        ));

        if ([] === $allowedStatuses) {
            return ['active' => false];
        }

        return [
            'active' => true,
            'mode' => 'picker',
            'statuses' => array_map($this->toItemArray(...), $allowedStatuses),
        ];
    }

    /**
     * Validates a statusUid submitted alongside a new comment on a status-less record. Only
     * ever returns a uid the user is actually allowed to set; the caller (CommentEditorController)
     * still owns rejecting the request outright when the client sent one it should not have, and
     * resolving "no statusUid submitted at all" to null before calling this - the only thing
     * left to decide here is whether the record is still eligible for the comment-first flow.
     *
     * @param array<string, mixed> $record
     */
    public function resolveStatusUidForCommentFirst(array $record, int $requestedStatusUid): ?int
    {
        if ($requestedStatusUid <= 0 || PlannerUtility::hasStatus($record)) {
            return null;
        }

        if (!$this->statusRepository->findByUid($requestedStatusUid) instanceof Status) {
            return null;
        }

        return $requestedStatusUid;
    }

    /**
     * @return array<string, mixed>
     */
    private function toItemArray(Status $status): array
    {
        return [
            'uid' => $status->getUid(),
            'title' => $status->getTitle(),
            'icon' => $status->getColoredIcon(),
        ];
    }
}
