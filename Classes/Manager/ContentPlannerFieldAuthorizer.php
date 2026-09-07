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

use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function array_key_exists;

/**
 * ContentPlannerFieldAuthorizer.
 *
 * Decides whether the current backend user may write the status and assignee values arriving
 * in a DataHandler field array, and strips the ones they may not.
 *
 * Kept apart from {@see StatusChangeManager} so that manager stays about *what happens* on a
 * status or assignee change, while the question of *who is allowed to* lives in one place.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ContentPlannerFieldAuthorizer
{
    /**
     * Unsetting a status and changing it to a specific one are separate permissions. Strips
     * both content planner fields and reports false when neither applies, so the DataHandler
     * writes nothing rather than a value the user may not set.
     *
     * @param array<string, mixed> $incomingFieldArray
     *
     * @return array{0: array<string, mixed>, 1: bool}
     */
    public function assertStatus(array $incomingFieldArray): array
    {
        $newStatusUid = $incomingFieldArray[Configuration::FIELD_STATUS];

        $allowed = null === $newStatusUid
            ? PermissionUtility::canUnsetStatus()
            : PermissionUtility::canChangeStatus((int) $newStatusUid);

        if (!$allowed) {
            unset($incomingFieldArray[Configuration::FIELD_STATUS], $incomingFieldArray[Configuration::FIELD_ASSIGNEE]);

            return [$incomingFieldArray, false];
        }

        return [$incomingFieldArray, true];
    }

    /**
     * A reassignment reaches the manager without a status change, so the assignee value has to
     * be authorised as well. Otherwise the only gate is that the backend does not render an
     * action URL for a target the user may not pick, which a hand-built request ignores.
     *
     * @param array<string, mixed> $incomingFieldArray
     * @param array<string, mixed> $preRecord
     *
     * @return array{0: array<string, mixed>, 1: bool}
     */
    public function assertAssignee(array $incomingFieldArray, array $preRecord): array
    {
        if (!array_key_exists(Configuration::FIELD_ASSIGNEE, $incomingFieldArray)) {
            return [$incomingFieldArray, true];
        }

        $rawAssignee = $incomingFieldArray[Configuration::FIELD_ASSIGNEE];
        $newAssignee = ('' === $rawAssignee || null === $rawAssignee)
            ? null
            : (int) $rawAssignee;
        $previousAssignee = isset($preRecord[Configuration::FIELD_ASSIGNEE]) && $preRecord[Configuration::FIELD_ASSIGNEE] > 0
            ? (int) $preRecord[Configuration::FIELD_ASSIGNEE]
            : null;
        $currentUserId = PermissionUtility::getCurrentUserId();

        // Clearing an assignment counts as touching your own when the assignment was yours.
        $touchesOwnAssignmentOnly = ($newAssignee === $currentUserId)
            || (null === $newAssignee && $previousAssignee === $currentUserId);

        $allowed = $touchesOwnAssignmentOnly
            ? PermissionUtility::canAssignSelf() || PermissionUtility::canAssignOthers()
            : PermissionUtility::canAssignOthers();

        if (!$allowed) {
            unset($incomingFieldArray[Configuration::FIELD_ASSIGNEE]);

            return [$incomingFieldArray, false];
        }

        return [$incomingFieldArray, true];
    }
}
