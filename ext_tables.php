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

use Xima\XimaTypo3ContentPlanner\Configuration;

defined('TYPO3') || exit;

$GLOBALS['TYPO3_USER_SETTINGS']['columns']['tx_ximatypo3contentplanner_hide'] = [
    'label' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximatypo3contentplanner_hide',
    'description' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:be_users.tx_ximatypo3contentplanner_hide.description',
    'type' => 'check',
    'table' => 'be_users',
];

$GLOBALS['TYPO3_USER_SETTINGS']['showitem'] .= ',
--div--;LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:content_planner,
    tx_ximatypo3contentplanner_hide,
    ';

$GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions'][Configuration::PERMISSION_GROUP] = [
    'header' => 'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.group',
    'items' => [
        // Legacy permission (backward compatibility) - enables basic feature visibility
        Configuration::PERMISSION_CONTENT_STATUS => [
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.content_status',
            'flag-black',
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.content_status.description',
        ],
        // Full access - grants all permissions at once
        Configuration::PERMISSION_FULL_ACCESS => [
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.full_access',
            'actions-check-circle-alt',
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.full_access.description',
        ],
        // Status permissions
        Configuration::PERMISSION_STATUS_CHANGE => [
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.status_change',
            'actions-edit',
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.status_change.description',
        ],
        Configuration::PERMISSION_STATUS_UNSET => [
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.status_unset',
            'actions-close',
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.status_unset.description',
        ],
        // Comment permissions
        Configuration::PERMISSION_COMMENT_EDIT_OWN => [
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_edit_own',
            'actions-open',
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_edit_own.description',
        ],
        Configuration::PERMISSION_COMMENT_EDIT_FOREIGN => [
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_edit_foreign',
            'actions-open',
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_edit_foreign.description',
        ],
        Configuration::PERMISSION_COMMENT_RESOLVE => [
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_resolve',
            'actions-check-circle',
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_resolve.description',
        ],
        Configuration::PERMISSION_COMMENT_DELETE_OWN => [
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_delete_own',
            'actions-delete',
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_delete_own.description',
        ],
        Configuration::PERMISSION_COMMENT_DELETE_FOREIGN => [
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_delete_foreign',
            'actions-delete',
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.comment_delete_foreign.description',
        ],
        // Assignment permissions
        Configuration::PERMISSION_ASSIGN_REASSIGN => [
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.assign_reassign',
            'actions-user',
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.assign_reassign.description',
        ],
        Configuration::PERMISSION_ASSIGN_OTHER_USER => [
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.assign_other_user',
            'actions-user',
            'LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_db.xlf:permission.assign_other_user.description',
        ],
    ],
];
