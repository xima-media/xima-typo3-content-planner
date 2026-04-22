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

namespace Xima\XimaTypo3ContentPlanner\Utility\Routing;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\{RouteResult, UriBuilder};
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\RouteUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Data\ContentUtility;

/**
 * UrlUtility.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class UrlUtility
{
    /**
     * @throws RouteNotFoundException
     */
    public static function getContentStatusPropertiesEditUrl(string $table, int $uid, bool $generateReturnUrl = true): string
    {
        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        $returnUrl = null;
        if ($generateReturnUrl) {
            /** @var RouteResult $routing */
            $routing = $request->getAttribute('routing');
            $route = $routing->getRoute();
            if (RouteUtility::isReturnUrlRelevantRoute($route->getOption('_identifier'))) {
                /** @var NormalizedParams $normalizedParams */
                $normalizedParams = $request->getAttribute('normalizedParams');
                $returnUrl = $normalizedParams->getRequestUri();
            }
        }

        $params = [
            'edit' => [$table => [$uid => 'edit']],
            'returnUrl' => $returnUrl,
            'columnsOnly' => [$table => [Configuration::FIELD_STATUS, Configuration::FIELD_ASSIGNEE, Configuration::FIELD_COMMENTS]],
        ];

        return (string) $uriBuilder->buildUriFromRoute('record_edit', $params);
    }

    public static function getNewCommentUrl(string $table, int $uid, ?int $parentCommentUid = null): string
    {
        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $pid = $uid;
        if ('pages' !== $table) {
            $record = ContentUtility::getExtensionRecord($table, $uid);
            $pid = (int) $record['pid'];
        }

        /** @var NormalizedParams $normalizedParams */
        $normalizedParams = $request->getAttribute('normalizedParams');
        $defVals = ['foreign_table' => $table, 'foreign_uid' => $uid];
        if (null !== $parentCommentUid) {
            $defVals['parent_uid'] = $parentCommentUid;
        }
        $params = [
            'edit' => [Configuration::TABLE_COMMENT => [$pid => 'new']],
            'returnUrl' => $normalizedParams->getRequestUri(),
            'defVals' => [Configuration::TABLE_COMMENT => $defVals],
        ];

        return (string) $uriBuilder->buildUriFromRoute('record_edit', $params);
    }

    /**
     * @throws RouteNotFoundException
     */
    public static function getEditCommentUrl(int $uid): string
    {
        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        /** @var NormalizedParams $normalizedParams */
        $normalizedParams = $request->getAttribute('normalizedParams');
        $params = [
            'edit' => [Configuration::TABLE_COMMENT => [$uid => 'edit']],
            'returnUrl' => $normalizedParams->getRequestUri(),
        ];

        return (string) $uriBuilder->buildUriFromRoute('record_edit', $params);
    }

    /**
     * @throws RouteNotFoundException
     */
    public static function getResolvedCommentUrl(int $uid, bool $isResolved): string
    {
        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        $isResolvedValue = $isResolved ? 0 : time();

        /** @var NormalizedParams $normalizedParams */
        $normalizedParams = $request->getAttribute('normalizedParams');
        $params = [
            'data' => [Configuration::TABLE_COMMENT => [$uid => ['resolved_date' => $isResolvedValue]]],
            'returnUrl' => $normalizedParams->getRequestUri(),
        ];

        return (string) $uriBuilder->buildUriFromRoute('tce_db', $params);
    }

    /**
     * @throws RouteNotFoundException
     */
    public static function getDeleteCommentUrl(int $uid): string
    {
        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        /** @var NormalizedParams $normalizedParams */
        $normalizedParams = $request->getAttribute('normalizedParams');
        $params = [
            'cmd' => [Configuration::TABLE_COMMENT => [$uid => ['delete' => 1]]],
            'returnUrl' => $normalizedParams->getRequestUri(),
        ];

        return (string) $uriBuilder->buildUriFromRoute('tce_db', $params);
    }

    /**
     * @param array<string, mixed> $extraParams
     *
     * @throws RouteNotFoundException
     */
    public static function getRecordLink(string $table, int $uid, ?string $folderIdentifier = null, array $extraParams = []): string
    {
        return match ($table) {
            'pages' => (string) GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('web_layout', ['id' => $uid, ...$extraParams]),
            Configuration::TABLE_FOLDER => self::getFolderLink($folderIdentifier, $extraParams),
            default => (string) GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('record_edit', ['edit' => [$table => [$uid => 'edit']], ...$extraParams]),
        };
    }

    /**
     * Get a link to the file list for a folder.
     *
     * @param array<string, mixed> $extraParams
     *
     * @throws RouteNotFoundException
     */
    public static function getFolderLink(?string $folderIdentifier, array $extraParams = []): string
    {
        if (null === $folderIdentifier || '' === $folderIdentifier) {
            return '';
        }

        return (string) GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('media_management', ['id' => $folderIdentifier, ...$extraParams]);
    }

    /**
     * @throws RouteNotFoundException
     */
    public static function getShareUrl(string $table, int $uid, ?int $commentUid = null): string
    {
        $params = ['table' => $table, 'uid' => $uid];
        if (null !== $commentUid) {
            $params['comment'] = $commentUid;
        }

        return (string) GeneralUtility::makeInstance(UriBuilder::class)->buildUriFromRoute('ximatypo3contentplanner_share', $params);
    }

    /**
     * @throws RouteNotFoundException
     */
    public static function assignToUser(string $table, int $uid, int|string|null $userId = null, bool $unassign = false): string
    {
        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        if (null === $userId) {
            /** @var BackendUserAuthentication $backendUser */
            $backendUser = $GLOBALS['BE_USER'];
            $userId = $backendUser->user['uid'];
            if (0 === $userId) {
                return '';
            }
        }

        if ($unassign) {
            $userId = '';
        }

        /** @var NormalizedParams $normalizedParams */
        $normalizedParams = $request->getAttribute('normalizedParams');
        $params = [
            'data' => [$table => [$uid => [Configuration::FIELD_ASSIGNEE => $userId]]],
            'redirect' => $normalizedParams->getRequestUri(),
        ];

        return (string) $uriBuilder->buildUriFromRoute('tce_db', $params);
    }
}
