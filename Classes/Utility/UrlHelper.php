<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Utility;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class UrlHelper
{
    /**
    * @throws RouteNotFoundException
    */
    public static function getContentStatusPropertiesEditUrl(string $table, int $uid, bool $generateReturnUrl = true): string
    {
        $request = $GLOBALS['TYPO3_REQUEST'];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $params = [
            'edit' => [$table => [$uid => 'edit']],
            'returnUrl' => $generateReturnUrl && in_array($request->getAttribute('routing')->getRoute()->getOption('_identifier'), ['web_layout', 'web_list', 'record_edit'], true) ? $request->getAttribute('normalizedParams')->getRequestUri() : null,
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

    /**
    * @throws RouteNotFoundException
    */
    public static function getEditCommentUrl(int $uid): string
    {
        $request = $GLOBALS['TYPO3_REQUEST'];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        $params = [
            'edit' => ['tx_ximatypo3contentplanner_comment' => [$uid => 'edit']],
            'returnUrl' => $request->getAttribute('normalizedParams')->getRequestUri(),
        ];
        return (string)$uriBuilder->buildUriFromRoute('record_edit', $params);
    }

    /**
    * @throws RouteNotFoundException
    */
    public static function getResolvedCommentUrl(int $uid, bool $isResolved): string
    {
        $request = $GLOBALS['TYPO3_REQUEST'];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        if ($isResolved) {
            $isResolvedValue = 0;
        } else {
            $isResolvedValue = time();
        }

        $params = [
            'data' => ['tx_ximatypo3contentplanner_comment' => [$uid => ['resolved_date' => $isResolvedValue]]],
            'returnUrl' => $request->getAttribute('normalizedParams')->getRequestUri(),
        ];
        return (string)$uriBuilder->buildUriFromRoute('tce_db', $params);
    }

    /**
    * @throws RouteNotFoundException
    */
    public static function getDeleteCommentUrl(int $uid): string
    {
        $request = $GLOBALS['TYPO3_REQUEST'];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        $params = [
            'cmd' => ['tx_ximatypo3contentplanner_comment' => [$uid => ['delete' => 1]]],
            'returnUrl' => $request->getAttribute('normalizedParams')->getRequestUri(),
        ];
        return (string)$uriBuilder->buildUriFromRoute('tce_db', $params);
    }

    /**
    * @throws RouteNotFoundException
    */
    public static function getRecordLink(string $table, int $uid): string
    {
        return match ($table) {
            'pages' => (string)GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('web_layout', ['id' => $uid]),
            default => (string)GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('record_edit', ['edit' => [$table => [$uid => 'edit']]]),
        };
    }

    /**
    * @throws RouteNotFoundException
    */
    public static function assignToUser(string $table, int $uid, int|string|null $userId = null, bool $unassign = false): string
    {
        $request = $GLOBALS['TYPO3_REQUEST'];
        /** @var ServerRequestInterface $request */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        if ($userId === null) {
            $userId = $GLOBALS['BE_USER']->user['uid'];
            if ($userId === 0) {
                return '';
            }
        }

        if ($unassign) {
            $userId = '';
        }

        $params = [
            'data' => [$table => [$uid => ['tx_ximatypo3contentplanner_assignee' => $userId]]],
            'redirect' => $request->getAttribute('normalizedParams')->getRequestUri(),
        ];
        return (string)$uriBuilder->buildUriFromRoute('tce_db', $params);
    }
}
