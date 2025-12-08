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

use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Imaging\IconFactory;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, FolderStatusRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;
use Xima\XimaTypo3ContentPlanner\Utility\PlannerUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Rendering\IconUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Routing\UrlUtility;

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
    ) {
        parent::__construct($statusRepository, $recordRepository, $statusSelectionManager, $commentRepository, $uriBuilder, $folderStatusRepository);
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

        $selectionEntriesToAdd[(string) $status->getUid()] = '<li><a class="dropdown-item" href="'.$href.'">'.$icon.' '.$title.'</a></li>';
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

        $selectionEntriesToAdd['reset'] = '<li><a class="dropdown-item" href="'.$href.'">'.$icon.' '.$title.'</a></li>';
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
     */
    public function addAssigneeItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        // Not implemented for list selection - only used in dropdown buttons
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
     */
    public function addCommentsItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $commentsCount = PlannerUtility::hasComments($record) ? (int) $record['tx_ximatypo3contentplanner_comments'] : 0;

        $icon = $this->iconFactory->getIcon('actions-message', IconUtility::getDefaultIconSize())->render();
        $href = UrlUtility::getContentStatusPropertiesEditUrl($table, $uid);
        $label = ($commentsCount > 0 ? $commentsCount.' ' : '').$this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:comments');

        $selectionEntriesToAdd['comments'] = '<li><a class="dropdown-item" href="'.$href.'">'.$icon.' '.htmlspecialchars($label, \ENT_QUOTES | \ENT_HTML5, 'UTF-8').'</a></li>';
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
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

        $selectionEntriesToAdd['commentsTodo'] = '<li><a class="dropdown-item" href="'.$href.'">'.$icon.' '.htmlspecialchars($label, \ENT_QUOTES | \ENT_HTML5, 'UTF-8').'</a></li>';
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

        $selectionEntriesToAdd[(string) $status->getUid()] = '<li><a class="dropdown-item" href="'.$href.'">'.$icon.' '.$title.'</a></li>';
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
        $title = htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:xima_typo3_content_planner/Resources/Private/Language/locallang_be.xlf:reset'), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $selectionEntriesToAdd['reset'] = '<li><a class="dropdown-item" href="'.$href.'">'.$icon.' '.$title.'</a></li>';
    }
}
