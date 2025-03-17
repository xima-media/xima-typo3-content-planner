<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Dto\StatusItem;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\RecordRepository;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Manager\StatusSelectionManager;
use Xima\XimaTypo3ContentPlanner\Utility\UrlHelper;

class ListSelectionService extends AbstractSelectionService implements SelectionInterface
{
    public function __construct(
        StatusRepository $statusRepository,
        RecordRepository $recordRepository,
        StatusSelectionManager $statusSelectionManager,
        UriBuilder $uriBuilder,
        private readonly IconFactory $iconFactory,
    ) {
        parent::__construct($statusRepository, $recordRepository, $statusSelectionManager, $uriBuilder);
    }

    public function addStatusItemToSelection(array &$selectionEntriesToAdd, Status $status, Status|int|null $currentStatus = null, ?string $table = null, array|int|null $uid = null, ?array $record = null): void
    {
        if ($this->compareStatus($status, $currentStatus)) {
            return;
        }
        $selectionEntriesToAdd[$status->getUid()] =
            sprintf(
                '<li><a class="dropdown-item dropdown-item-spaced" href="%s" title="%s">%s%s</a></li>',
                htmlspecialchars($this->buildUriForStatusChange($table, $uid, $status, $record['pid'])->__toString()),
                $status->getTitle(),
                $this->iconFactory->getIcon($status->getColoredIcon(), Icon::SIZE_SMALL)->render(),
                $status->getTitle()
            )
        ;
    }

    public function addDividerItemToSelection(array &$selectionEntriesToAdd, ?string $additionalPostIdentifier = null): void
    {
        $selectionEntriesToAdd['divider' . $additionalPostIdentifier ?? ''] = '<li><hr class="dropdown-divider"></li>';
    }

    public function addStatusResetItemToSelection(array &$selectionEntriesToAdd, ?string $table = null, array|int|null $uid = null, ?array $record = null): void
    {
        $selectionEntriesToAdd['reset'] =
            sprintf(
                '<li><a class="dropdown-item dropdown-item-spaced" href="%s" title="%s">%s%s</a></li>',
                htmlspecialchars($this->buildUriForStatusChange($table, $uid, null, $record['pid'])->__toString()),
                $this->getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:reset'),
                $this->iconFactory->getIcon('actions-close', Icon::SIZE_SMALL)->render(),
                $this->getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:reset')
            )
        ;
    }

    public function addAssigneeItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        if (!$record['tx_ximatypo3contentplanner_assignee']) {
            return;
        }
        $statusItem = StatusItem::create($record);
        $selectionEntriesToAdd['assignee'] =
            sprintf(
                '<li><a class="dropdown-item dropdown-item-spaced" href="%s" title="%s">%s%s</a></li>',
                htmlspecialchars(UrlHelper::getContentStatusPropertiesEditUrl($table, $uid)),
                $statusItem->getAssigneeName(),
                $statusItem->getAssigneeAvatar(),
                $statusItem->getAssigneeName()
            )
        ;
    }

    public function addCommentsItemToSelection(array &$selectionEntriesToAdd, array $record, ?string $table = null, ?int $uid = null): void
    {
        $selectionEntriesToAdd['comments'] =
            sprintf(
                '<li><a class="dropdown-item dropdown-item-spaced contentPlanner--comments" href="#" data-force-ajax-url data-content-planner-comments data-table="%s" data-id="%s" data-new-comment-uri="%s">%s%s</a></li>',
                $table,
                $uid,
                UrlHelper::getNewCommentUrl($table, $uid),
                $this->iconFactory->getIcon('content-message', Icon::SIZE_SMALL)->render(),
                $this->getLanguageService()->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:comments') . ($record['tx_ximatypo3contentplanner_comments'] ? ' (' . $record['tx_ximatypo3contentplanner_comments'] . ')' : '')
            )
        ;
    }
}
