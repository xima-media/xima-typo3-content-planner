<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Utility;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class UrlHelper
{
    public static function getContentStatusPropertiesEditUrl(string $table, int $uid, bool $generateReturnUrl = true): string
    {
        $request = $GLOBALS['TYPO3_REQUEST'];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $params = [
            'edit' => [$table => [$uid => 'edit']],
            'returnUrl' => $generateReturnUrl ? $request->getAttribute('normalizedParams')->getRequestUri() : null,
            'columnsOnly' => 'tx_ximatypo3contentplanner_status,tx_ximatypo3contentplanner_assignee,tx_ximatypo3contentplanner_comments',
        ];
        return (string)$uriBuilder->buildUriFromRoute('record_edit', $params);
    }

    public static function getNewCommentUrl(string $table, int $uid): string
    {
        $request = $GLOBALS['TYPO3_REQUEST'];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $pid = $uid;
        if ($table !== 'pages') {
            $record = ContentUtility::getExtensionRecord($table, $uid);
            $pid = (int)$record['pid'];
        }

        $params = [
            'edit' => ['tx_ximatypo3contentplanner_comment' => [$pid => 'new']],
            'returnUrl' => $request->getAttribute('normalizedParams')->getRequestUri(),
            'defVals' => ['tx_ximatypo3contentplanner_comment' => ['foreign_table' => $table, 'foreign_uid' => $uid]],
        ];
        return (string)$uriBuilder->buildUriFromRoute('record_edit', $params);
    }

    public static function getRecordLink(string $table, int $uid): string
    {
        return match ($table) {
            'pages' => (string)GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('web_layout', ['id' => $uid]),
            default => (string)GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('record_edit', ['edit' => [$table => [$uid => 'edit']]]),
        };
    }
}
