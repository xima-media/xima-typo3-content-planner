<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2025 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Imaging\IconFactory;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{BackendUserRepository, CommentRepository, FolderStatusRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;
use Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\IconUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Routing\UrlUtility;

use function sprintf;

/**
 * ListSelectionService.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
class ListSelectionService extends AbstractSelectionService implements SelectionInterface
{
    public function __construct(
        StatusRepository $statusRepository,
        RecordRepository $recordRepository,
        StatusSelectionManager $statusSelectionManager,
        UriBuilder $uriBuilder,
        CommentRepository $commentRepository,
        FolderStatusRepository $folderStatusRepository,
        private readonly IconFactory $iconFactory,
        private readonly BackendUserRepository $backendUserRepository,
    ) {
        parent::__construct($statusRepository, $recordRepository, $statusSelectionManager, $commentRepository, $uriBuilder, $folderStatusRepository);
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     */
    public function addHeaderItemToSelection(array &$selectionEntriesToAdd): void
    {
        $title = $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:status');

        $selectionEntriesToAdd['header'] = sprintf(
            '<li><h6 class="dropdown-header"><strong>%s</strong></h6></li>',
            htmlspecialchars($title, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'),
        );
        $selectionEntriesToAdd['headerDivider'] = '<li><hr class="dropdown-divider"></li>';
    }

    /**
     * @param array<string|int, mixed>       $selectionEntriesToAdd
     * @param array<int>|int|null            $uid
     * @param array<string, mixed>|bool|null $record
     *
     * @throws RouteNotFoundException
     */
    public function addStatusItemToSelection(array &$selectionEntriesToAdd, Status $status, Status|int|null $currentStatus = null, ?string $table = null, array|int|null $uid = null, array|bool|null $record = null): void
    {
        if ($this->compareStatus($status, $currentStatus)) {
            return;
        }

        $icon = $this->iconFactory->getIcon($status->getColoredIcon(), IconUtility::getDefaultIconSize())->render();
        $href = $this->buildUriForStatusChange($table, $uid, $status)->__toString();
        $title = htmlspecialchars($status->getTitle(), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $selectionEntriesToAdd[(string) $status->getUid()] = sprintf(
            '<li><a class="dropdown-item" href="%s" data-content-planner-status-change="true">%s %s</a></li>',
            $href,
            $icon,
            $title,
        );
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     */
    public function addDividerItemToSelection(array &$selectionEntriesToAdd, ?string $additionalPostIdentifier = null): void
    {
        $selectionEntriesToAdd['divider'.($additionalPostIdentifier ?? '')] = '<li><hr class="dropdown-divider"></li>';
    }

    /**
     * @param array<string, mixed>           $selectionEntriesToAdd
     * @param array<int, int>|int|null       $uid
     * @param array<string, mixed>|bool|null $record
     *
     * @throws RouteNotFoundException
     */
    public function addStatusResetItemToSelection(array &$selectionEntriesToAdd, ?string $table = null, array|int|null $uid = null, array|bool|null $record = null): void
    {
        $icon = $this->iconFactory->getIcon('actions-close', IconUtility::getDefaultIconSize())->render();
        $href = $this->buildUriForStatusChange($table, $uid, null)->__toString();
        $title = htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:reset'), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $selectionEntriesToAdd['reset'] = sprintf(
            '<li><a class="dropdown-item" href="%s" data-content-planner-status-change="true" data-content-planner-status-reset="true">%s %s</a></li>',
            $href,
            $icon,
            $title,
        );
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
     */
    public function addAssigneeItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $currentAssignee = (int) $record[Configuration::FIELD_ASSIGNEE];
        $username = $this->backendUserRepository->getUsernameByUid($currentAssignee);
        $label = '' !== $username
            ? $username
            : $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:header.unassigned');

        $icon = $this->iconFactory->getIcon('actions-user', IconUtility::getDefaultIconSize())->render();
        $href = UrlUtility::getContentStatusPropertiesEditUrl($table, $uid);
        $escapedLabel = htmlspecialchars($label, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $selectionEntriesToAdd['assignee'] = sprintf(
            '<li><a class="dropdown-item" href="%s" data-content-planner-assignees="true" data-force-ajax-url="true" data-table="%s" data-id="%d" data-current-assignee="%d">%s %s</a></li>',
            $href,
            $table,
            $uid,
            $currentAssignee,
            $icon,
            $escapedLabel,
        );
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
     *
     * @throws RouteNotFoundException
     */
    public function addCommentsItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $commentsCount = PlannerUtility::hasComments($record) ? (int) $record[Configuration::FIELD_COMMENTS] : 0;

        $icon = $this->iconFactory->getIcon('actions-message', IconUtility::getDefaultIconSize())->render();
        $href = UrlUtility::getContentStatusPropertiesEditUrl($table, $uid);
        $label = ($commentsCount > 0 ? $commentsCount.' ' : '').$this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:comments');
        $newCommentUri = htmlspecialchars(UrlUtility::getNewCommentUrl($table, $uid), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $editUri = htmlspecialchars($href, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $escapedLabel = htmlspecialchars($label, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $selectionEntriesToAdd['comments'] = sprintf(
            '<li><a class="dropdown-item" href="%s" data-content-planner-comments="true" data-force-ajax-url="true" data-table="%s" data-id="%d" data-new-comment-uri="%s" data-edit-uri="%s">%s %s</a></li>',
            $href,
            $table,
            $uid,
            $newCommentUri,
            $editUri,
            $icon,
            $escapedLabel,
        );
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
     *
     * @throws RouteNotFoundException
     */
    public function addCommentsTodoItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $todoTotal = $this->getCommentsTodoTotal($record, $table);
        if (0 === $todoTotal) {
            return;
        }

        $todoResolved = $this->getCommentsTodoResolved($record, $table);
        $icon = $this->iconFactory->getIcon('actions-check-square', IconUtility::getDefaultIconSize())->render();
        $href = UrlUtility::getContentStatusPropertiesEditUrl($table, $uid);
        $label = "$todoResolved/$todoTotal ".$this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:comments.todo');
        $newCommentUri = htmlspecialchars(UrlUtility::getNewCommentUrl($table, $uid), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $editUri = htmlspecialchars($href, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $escapedLabel = htmlspecialchars($label, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $selectionEntriesToAdd['commentsTodo'] = sprintf(
            '<li><a class="dropdown-item" href="%s" data-content-planner-comments="true" data-force-ajax-url="true" data-table="%s" data-id="%d" data-new-comment-uri="%s" data-edit-uri="%s">%s %s</a></li>',
            $href,
            $table,
            $uid,
            $newCommentUri,
            $editUri,
            $icon,
            $escapedLabel,
        );
    }

    /**
     * @param array<int|string, mixed> $selectionEntriesToAdd
     *
     * @throws RouteNotFoundException
     */
    public function addFolderStatusItemToSelection(array &$selectionEntriesToAdd, Status $status, ?int $currentStatus, string $combinedIdentifier): void
    {
        if (null !== $currentStatus && $status->getUid() === $currentStatus) {
            return;
        }

        $icon = $this->iconFactory->getIcon($status->getColoredIcon(), IconUtility::getDefaultIconSize())->render();
        $href = $this->buildUriForFolderStatusChange($combinedIdentifier, $status)->__toString();
        $title = htmlspecialchars($status->getTitle(), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $selectionEntriesToAdd[(string) $status->getUid()] = sprintf(
            '<li><a class="dropdown-item" href="%s" data-content-planner-status-change="true">%s %s</a></li>',
            $href,
            $icon,
            $title,
        );
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     *
     * @throws RouteNotFoundException
     */
    public function addFolderStatusResetItemToSelection(array &$selectionEntriesToAdd, string $combinedIdentifier): void
    {
        $icon = $this->iconFactory->getIcon('actions-close', IconUtility::getDefaultIconSize())->render();
        $href = $this->buildUriForFolderStatusChange($combinedIdentifier, null)->__toString();
        $title = htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:reset'), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $selectionEntriesToAdd['reset'] = sprintf(
            '<li><a class="dropdown-item" href="%s" data-content-planner-status-change="true" data-content-planner-status-reset="true">%s %s</a></li>',
            $href,
            $icon,
            $title,
        );
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $folderRecord
     *
     * @throws Exception
     * @throws RouteNotFoundException
     */
    public function addFolderAssigneeItemToSelection(array &$selectionEntriesToAdd, array $folderRecord, string $combinedIdentifier): void
    {
        $table = Configuration::TABLE_FOLDER;
        $uid = (int) $folderRecord['uid'];
        $currentAssignee = (int) ($folderRecord[Configuration::FIELD_ASSIGNEE] ?? 0);

        $username = $this->backendUserRepository->getUsernameByUid($currentAssignee);
        $label = '' !== $username
            ? $username
            : $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:header.unassigned');

        $icon = $this->iconFactory->getIcon('actions-user', IconUtility::getDefaultIconSize())->render();
        $href = UrlUtility::getContentStatusPropertiesEditUrl($table, $uid);
        $escapedLabel = htmlspecialchars($label, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $selectionEntriesToAdd['assignee'] = sprintf(
            '<li><a class="dropdown-item" href="%s" data-content-planner-assignees="true" data-force-ajax-url="true" data-table="%s" data-id="%d" data-current-assignee="%d">%s %s</a></li>',
            $href,
            $table,
            $uid,
            $currentAssignee,
            $icon,
            $escapedLabel,
        );
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $folderRecord
     *
     * @throws RouteNotFoundException
     * @throws Exception
     */
    public function addFolderCommentsItemToSelection(array &$selectionEntriesToAdd, array $folderRecord, string $combinedIdentifier): void
    {
        $table = Configuration::TABLE_FOLDER;
        $uid = (int) $folderRecord['uid'];

        $commentsCount = PlannerUtility::hasComments($folderRecord) ? $this->commentRepository->countAllByRecord($uid, $table) : 0;

        $icon = $this->iconFactory->getIcon('actions-message', IconUtility::getDefaultIconSize())->render();
        $href = UrlUtility::getContentStatusPropertiesEditUrl($table, $uid);
        $label = ($commentsCount > 0 ? $commentsCount.' ' : '').$this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:comments');
        $newCommentUri = htmlspecialchars(UrlUtility::getNewCommentUrl($table, $uid), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $editUri = htmlspecialchars($href, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $escapedLabel = htmlspecialchars($label, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $selectionEntriesToAdd['comments'] = sprintf(
            '<li><a class="dropdown-item" href="%s" data-content-planner-comments="true" data-force-ajax-url="true" data-table="%s" data-id="%d" data-new-comment-uri="%s" data-edit-uri="%s">%s %s</a></li>',
            $href,
            $table,
            $uid,
            $newCommentUri,
            $editUri,
            $icon,
            $escapedLabel,
        );
    }
}
