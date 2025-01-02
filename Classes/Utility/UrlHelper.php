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
}
