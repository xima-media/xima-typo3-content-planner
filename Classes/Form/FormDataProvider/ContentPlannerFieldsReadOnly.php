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

namespace Xima\XimaTypo3ContentPlanner\Form\FormDataProvider;

use TYPO3\CMS\Backend\Form\FormDataProviderInterface;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function array_key_exists;
use function in_array;

/**
 * ContentPlannerFieldsReadOnly.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ContentPlannerFieldsReadOnly implements FormDataProviderInterface
{
    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     *
     * @phpstan-ignore method.childParameterType (Interface uses untyped array)
     */
    public function addData(array $result): array
    {
        if (!in_array($result['tableName'], ExtensionUtility::getRecordTables(), true)) {
            return $result;
        }

        $columns = &$result['processedTca']['columns'];

        if (array_key_exists(Configuration::FIELD_STATUS, $columns) && !PermissionUtility::canChangeStatus()) {
            $columns[Configuration::FIELD_STATUS]['config']['readOnly'] = true;
        }

        if (array_key_exists(Configuration::FIELD_ASSIGNEE, $columns) && !$this->canModifyAssignee()) {
            $columns[Configuration::FIELD_ASSIGNEE]['config']['readOnly'] = true;
        }

        if (array_key_exists(Configuration::FIELD_COMMENTS, $columns) && !PermissionUtility::canCreateComment()) {
            $columns[Configuration::FIELD_COMMENTS]['config']['readOnly'] = true;
        }

        return $result;
    }

    private function canModifyAssignee(): bool
    {
        return PermissionUtility::canAssignOtherUser()
            || PermissionUtility::canReassign();
    }
}
