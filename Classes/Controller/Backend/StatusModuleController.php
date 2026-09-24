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

namespace Xima\XimaTypo3ContentPlanner\Controller\Backend;

use Doctrine\DBAL\Exception;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\{HtmlResponse, RedirectResponse};
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;

use function array_keys;
use function array_map;
use function array_search;
use function count;

/**
 * StatusModuleController.
 *
 * Status administration submodule (CP-38, #411): the Content Planner module's second entry
 * point after Records (#404). Statuses are `adminOnly`/`rootLevel => 1` in their own TCA, which
 * kept them reachable only by navigating to pid 0 in the core List module - this gives them a
 * dedicated, discoverable home instead.
 *
 * Deliberately queries the status table directly rather than going through
 * {@see \Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository}: that repository's
 * `findAll()` is cached and excludes hidden statuses (the default TCA restriction), correct for
 * its own callers (status dropdowns, selection UIs) but wrong here - the admin list must show
 * every status, hidden ones included and marked as such. The cached `Status` DTO is also part of
 * this extension's public PSR-14 event API, not something to extend just for an admin-only
 * `hidden` flag.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final readonly class StatusModuleController
{
    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private UriBuilder $uriBuilder,
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * @throws Exception
     */
    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->getBackendUser()->isAdmin()) {
            return new HtmlResponse('', 403);
        }

        $statuses = $this->findAllStatusesIncludingHidden();
        $lastIndex = count($statuses) - 1;
        $rows = array_map(fn (array $status, int $index): array => $status + [
            'editUrl' => $this->buildEditUrl($status['uid'], 'edit'),
            'deleteUrl' => $this->buildDeleteUrl($status['uid']),
            'moveUpUrl' => $index > 0 ? $this->buildMoveUrl($status['uid'], 'up') : null,
            'moveDownUrl' => $index < $lastIndex ? $this->buildMoveUrl($status['uid'], 'down') : null,
        ], $statuses, array_keys($statuses));

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle($this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/Modules/status.xlf:title'));
        $moduleTemplate->assignMultiple([
            'statuses' => $rows,
            'newUrl' => $this->buildEditUrl(0, 'new'),
        ]);

        return $moduleTemplate->renderResponse('Backend/Modules/Status/Index');
    }

    /**
     * Moves the given status past its neighbour in the requested direction, through
     * DataHandler's dedicated `move` command - the only way this list's own reordering reaches
     * the same cache-invalidation hook a FormEngine save does. A `sortby`-configured field
     * (`sorting` here) is not writable through a plain datamap field value at all; DataHandler
     * manages it exclusively through `cmd[table][uid]['move']` (negative: place directly after
     * that uid; non-negative: place first within that pid).
     *
     * @throws Exception
     */
    public function moveAction(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->getBackendUser()->isAdmin()) {
            return new HtmlResponse('', 403);
        }

        $queryParams = $request->getQueryParams();
        $uid = (int) ($queryParams['uid'] ?? 0);
        $direction = (string) ($queryParams['direction'] ?? '');

        $statuses = $this->findAllStatusesIncludingHidden();
        $uids = array_map(static fn (array $status): int => $status['uid'], $statuses);
        $position = array_search($uid, $uids, true);

        if (false !== $position) {
            if ('up' === $direction && isset($uids[$position - 1])) {
                $destination = $position - 2 >= 0 ? -$uids[$position - 2] : 0;
                $this->moveStatus($uid, $destination);
            } elseif ('down' === $direction && isset($uids[$position + 1])) {
                $this->moveStatus($uid, -$uids[$position + 1]);
            }
        }

        return new RedirectResponse($this->buildIndexUrl());
    }

    /**
     * @return list<array{uid: int, title: string, icon: string, color: string, isDefault: bool, hidden: bool, sorting: int}>
     *
     * @throws Exception
     */
    private function findAllStatusesIncludingHidden(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(Configuration::TABLE_STATUS);
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);

        $rows = $queryBuilder
            ->select('uid', 'title', 'icon', 'color', 'hidden', 'sorting', Configuration::FIELD_STATUS_IS_DEFAULT)
            ->from(Configuration::TABLE_STATUS)
            ->orderBy('sorting', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static fn (array $row): array => [
            'uid' => (int) $row['uid'],
            'title' => (string) $row['title'],
            'icon' => (string) $row['icon'],
            'color' => (string) $row['color'],
            'isDefault' => (bool) $row[Configuration::FIELD_STATUS_IS_DEFAULT],
            'hidden' => (bool) $row['hidden'],
            'sorting' => (int) $row['sorting'],
        ], $rows);
    }

    private function moveStatus(int $uid, int $destination): void
    {
        /** @var DataHandler $dataHandler */
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([], [
            Configuration::TABLE_STATUS => [
                $uid => ['move' => $destination],
            ],
        ]);
        $dataHandler->process_cmdmap();
    }

    private function buildIndexUrl(): string
    {
        return (string) $this->uriBuilder->buildUriFromRoute('content_planner_status');
    }

    private function buildEditUrl(int $uidOrPid, string $action): string
    {
        return (string) $this->uriBuilder->buildUriFromRoute('record_edit', [
            'edit' => [Configuration::TABLE_STATUS => [$uidOrPid => $action]],
            'returnUrl' => $this->buildIndexUrl(),
        ]);
    }

    private function buildDeleteUrl(int $uid): string
    {
        return (string) $this->uriBuilder->buildUriFromRoute('tce_db', [
            'cmd' => [Configuration::TABLE_STATUS => [$uid => ['delete' => true]]],
            'redirect' => $this->buildIndexUrl(),
        ]);
    }

    private function buildMoveUrl(int $uid, string $direction): string
    {
        // AJAX routes (Configuration/Backend/AjaxRoutes.php) are addressed with an 'ajax_'
        // prefix on their route identifier when building a URI from PHP; see
        // UrlUtility::getShareUrl() for the same convention.
        return (string) $this->uriBuilder->buildUriFromRoute('ajax_ximatypo3contentplanner_statusmove', [
            'uid' => $uid,
            'direction' => $direction,
        ]);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        /** @var BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];

        return $backendUser;
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
