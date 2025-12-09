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

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use TYPO3\CMS\Backend\Controller\Event\AfterFileStorageTreeItemsPreparedEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Folder;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Model\Status;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\{FolderStatusRepository, StatusRepository};
use Xima\XimaTypo3ContentPlanner\Utility\Compatibility\VersionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\Security\PermissionUtility;

/**
 * AfterFileStorageTreeItemsPreparedListener.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
#[AsEventListener(
    identifier: 'xima-typo3-content-planner/backend/modify-file-storage-tree-items',
    event: AfterFileStorageTreeItemsPreparedEvent::class,
)]
final readonly class AfterFileStorageTreeItemsPreparedListener
{
    public function __construct(
        private StatusRepository $statusRepository,
        private FolderStatusRepository $folderStatusRepository,
    ) {}

    /**
     * @param object $event AfterFileStorageTreeItemsPreparedEvent (TYPO3 v14+)
     */
    public function __invoke(object $event): void
    {
        // This listener only works in TYPO3 v14+
        if (!VersionUtility::is14OrHigher()) {
            return;
        }

        if (!PermissionUtility::checkContentStatusVisibility() || !ExtensionUtility::isFilelistSupportEnabled()) {
            return;
        }

        $items = $event->getItems();

        foreach ($items as &$item) {
            $resource = $item['resource'] ?? null;
            if (!$resource instanceof Folder) {
                continue;
            }

            $combinedIdentifier = $resource->getCombinedIdentifier();
            $folderStatus = $this->folderStatusRepository->findByCombinedIdentifier($combinedIdentifier);

            if (false === $folderStatus || !isset($folderStatus['tx_ximatypo3contentplanner_status']) || 0 === (int) $folderStatus['tx_ximatypo3contentplanner_status']) {
                $this->applyEmptyLabelWorkaround($item);
                continue;
            }

            $this->applyStatusToItem($item, (int) $folderStatus['tx_ximatypo3contentplanner_status']);
        }

        $event->setItems($items);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function applyStatusToItem(array &$item, int $statusUid): void
    {
        $status = $this->statusRepository->findByUid($statusUid);
        if (!$status instanceof Status) {
            return;
        }

        // TYPO3\CMS\Backend\Dto\Tree\Label\Label only exists in v14+
        $item['labels'][] = new \TYPO3\CMS\Backend\Dto\Tree\Label\Label(
            label: $status->getTitle(),
            color: Configuration\Colors::get($status->getColor()),
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function applyEmptyLabelWorkaround(array &$item): void
    {
        // Workaround for label behavior
        // Labels will be inherited from parent folders, if not set explicitly
        $item['labels'][] = new \TYPO3\CMS\Backend\Dto\Tree\Label\Label(
            label: '',
            color: 'inherit',
        );
    }
}
