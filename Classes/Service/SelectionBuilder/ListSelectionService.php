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
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\StatusItem;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{CommentRepository, RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;
use Xima\XimaTypo3ContentPlanner\Utility\{IconHelper, UrlHelper};

use function is_array;
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
        private readonly CommentRepository $commentRepository,
        private readonly IconFactory $iconFactory,
    ) {
        parent::__construct($statusRepository, $recordRepository, $statusSelectionManager, $commentRepository, $uriBuilder);
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
        if ($this->compareStatus($status, $currentStatus) || !is_array($record)) {
            return;
        }
        $selectionEntriesToAdd[(string) $status->getUid()] =
            sprintf(
                '<li><a class="dropdown-item dropdown-item-spaced" href="%s" title="%s">%s%s</a></li>',
                htmlspecialchars($this->buildUriForStatusChange($table, $uid, $status, $record['pid'])->__toString(), \ENT_QUOTES | \ENT_HTML5),
                $status->getTitle(),
                $this->iconFactory->getIcon($status->getColoredIcon(), IconHelper::getDefaultIconSize())->render(),
                $status->getTitle(),
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
        $selectionEntriesToAdd['reset'] =
            sprintf(
                '<li><a class="dropdown-item dropdown-item-spaced" href="%s" title="%s">%s%s</a></li>',
                htmlspecialchars($this->buildUriForStatusChange($table, $uid, null, $record['pid'])->__toString(), \ENT_QUOTES | \ENT_HTML5),
                $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:reset'),
                $this->iconFactory->getIcon('actions-close', IconHelper::getDefaultIconSize())->render(),
                $this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:reset'),
            );
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
     *
     * @throws RouteNotFoundException
     */
    public function addAssigneeItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        if (!isset($record['tx_ximatypo3contentplanner_assignee']) || !is_numeric($record['tx_ximatypo3contentplanner_assignee']) || 0 === $record['tx_ximatypo3contentplanner_assignee']) {
            return;
        }
        $statusItem = StatusItem::create($record);
        $selectionEntriesToAdd['assignee'] =
            sprintf(
                '<li><a class="dropdown-item dropdown-item-spaced" href="%s" title="%s">%s%s</a></li>',
                htmlspecialchars(UrlHelper::getContentStatusPropertiesEditUrl($table, $uid), \ENT_QUOTES | \ENT_HTML5),
                $statusItem->getAssigneeName(),
                $statusItem->getAssigneeAvatar(),
                $statusItem->getAssigneeName(),
            );
    }

    /**
     * @param array<string, mixed> $selectionEntriesToAdd
     * @param array<string, mixed> $record
     *
     * @throws Exception|RouteNotFoundException
     */
    public function addCommentsItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $selectionEntriesToAdd['comments'] =
            sprintf(
                '<li><a class="dropdown-item dropdown-item-spaced contentPlanner--comments" href="#" data-force-ajax-url data-content-planner-comments data-table="%s" data-id="%s" data-new-comment-uri="%s" data-edit-uri="%s">%s%s</a></li>',
                $table,
                $uid,
                UrlHelper::getNewCommentUrl($table, $uid),
                UrlHelper::getContentStatusPropertiesEditUrl($table, $uid),
                $this->iconFactory->getIcon('content-message', IconHelper::getDefaultIconSize())->render(),
                (isset($record['tx_ximatypo3contentplanner_comments']) && is_numeric($record['tx_ximatypo3contentplanner_comments']) && $record['tx_ximatypo3contentplanner_comments'] > 0 ? $this->commentRepository->countAllByRecord($record['uid'], $table).' ' : '').$this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:comments'),
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

        $selectionEntriesToAdd['commentsTodo'] =
            sprintf(
                '<li><a class="dropdown-item dropdown-item-spaced contentPlanner--comments" href="#" data-force-ajax-url data-content-planner-comments data-table="%s" data-id="%s" data-new-comment-uri="%s" data-edit-uri="%s">%s%s</a></li>',
                $table,
                $uid,
                UrlHelper::getNewCommentUrl($table, $uid),
                UrlHelper::getContentStatusPropertiesEditUrl($table, $uid),
                $this->iconFactory->getIcon('actions-check-square', IconHelper::getDefaultIconSize())->render(),
                "$todoResolved/$todoTotal ".$this->getLanguageService()->sL('LLL:EXT:'.Configuration::EXT_KEY.'/Resources/Private/Language/locallang_be.xlf:comments.todo'),
            );
    }
}
