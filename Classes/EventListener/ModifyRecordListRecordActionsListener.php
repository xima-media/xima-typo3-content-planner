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

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use TYPO3\CMS\Backend\RecordList\Event\ModifyRecordListRecordActionsEvent;
use TYPO3\CMS\Backend\Template\Components\ActionGroup;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Domain\RecordInterface;
use TYPO3\CMS\Core\Imaging\{IconFactory, IconSize};
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{RecordRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Service\SelectionBuilder\DropDownSelectionService;
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\{ComponentFactoryUtility, VersionUtility};
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

use function count;
use function is_array;

/**
 * ModifyRecordListRecordActionsListener.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
#[AsEventListener(identifier: 'xima-typo3-content-planner/backend/modify-record-list-record-actions')]
final readonly class ModifyRecordListRecordActionsListener
{
    public function __construct(
        private IconFactory $iconFactory,
        private StatusRepository $statusRepository,
        private RecordRepository $recordRepository,
        private DropDownSelectionService $dropDownSelectionService,
    ) {}

    // @phpstan-ignore-next-line complexity.functionLike
    public function __invoke(ModifyRecordListRecordActionsEvent $event): void
    {
        if (!PermissionUtility::checkContentStatusVisibility()) {
            return;
        }
        // TYPO3 v13/v14 compatibility: In v14 getRecord() returns RecordInterface, in v13 it returns array
        // @phpstan-ignore instanceof.alwaysTrue, method.notFound
        $table = $event->getRecord() instanceof RecordInterface ? $event->getRecord()->getMainType() : $event->getTable();

        if (!ExtensionUtility::isRegisteredRecordTable($table) || $event->hasAction('Status')) {
            return;
        }

        $allStatus = $this->statusRepository->findAll();
        if (0 === count($allStatus)) {
            return;
        }

        // TYPO3 v13/v14 compatibility: In v14 getRecord() returns RecordInterface, in v13 it returns array
        // @phpstan-ignore instanceof.alwaysTrue
        $uid = $event->getRecord() instanceof RecordInterface ? $event->getRecord()->getUid() : $event->getRecord()['uid'];

        // ToDo: this is necessary cause the status is not in the record, pls check tca for this
        $record = $this->recordRepository->findByUid($table, $uid, ignoreVisibilityRestriction: true);
        if (!is_array($record)) {
            return;
        }

        $statusId = $record[Configuration::FIELD_STATUS];
        $status = $this->statusRepository->findByUid($statusId);

        $title = $status instanceof Status ? $status->getTitle() : 'Status';
        $icon = $status instanceof Status ? $status->getColoredIcon() : 'flag-gray';

        $dropDownButton = ComponentFactoryUtility::createDropDownButton()
            ->setLabel($title)
            ->setTitle($title)
            ->setIcon($this->iconFactory->getIcon($icon, IconSize::SMALL));

        $actionsToAdd = $this->dropDownSelectionService->generateSelection($table, $uid);
        foreach ($actionsToAdd as $actionToAdd) {
            $dropDownButton->addItem($actionToAdd);
        }

        $event->setAction(
            VersionUtility::is14OrHigher() ? $dropDownButton : $dropDownButton->render(),
            'Status',
            VersionUtility::is14OrHigher() ? ActionGroup::primary : 'primary',
            'delete',
        );
    }
}
