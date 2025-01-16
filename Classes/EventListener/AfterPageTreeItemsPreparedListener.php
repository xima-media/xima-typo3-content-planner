<?php

declare(strict_types=1);

namespace Xima\XimaTypo3ContentPlanner\EventListener;

use TYPO3\CMS\Backend\Controller\Event\AfterPageTreeItemsPreparedEvent;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use Xima\XimaTypo3ContentPlanner\Configuration;
use Xima\XimaTypo3ContentPlanner\Domain\Repository\StatusRepository;
use Xima\XimaTypo3ContentPlanner\Utility\ExtensionUtility;
use Xima\XimaTypo3ContentPlanner\Utility\VisibilityUtility;

/*
* https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ApiOverview/Events/Events/Backend/AfterPageTreeItemsPreparedEvent.html
*/
final class AfterPageTreeItemsPreparedListener
{
    public function __construct(protected readonly StatusRepository $statusRepository)
    {
    }

    public function __invoke(AfterPageTreeItemsPreparedEvent $event): void
    {
        if (!VisibilityUtility::checkContentStatusVisibility()) {
            return;
        }

        $items = $event->getItems();
        foreach ($items as &$item) {
            $status = null;
            $version = VersionNumberUtility::getCurrentTypo3Version();
            if (isset($item['_page']['tx_ximatypo3contentplanner_status'])) {
                $status = $this->statusRepository->findByUid($item['_page']['tx_ximatypo3contentplanner_status']);
                if ($status) {
                    if (version_compare($version, '13.0.0', '>=')) {
                        // @phpstan-ignore-next-line
                        $item['labels'][] = new \TYPO3\CMS\Backend\Dto\Tree\Label\Label(
                            label: $status->getTitle(),
                            color: Configuration\Colors::get($status->getColor()),
                        );

                        if (ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_TREE_STATUS_INFORMATION)
                            && isset($item['_page']['tx_ximatypo3contentplanner_comments'])
                            && $item['_page']['tx_ximatypo3contentplanner_comments'] > 0
                        ) {
                            // @phpstan-ignore-next-line
                            $item['statusInformation'][] = new \TYPO3\CMS\Backend\Dto\Tree\Status\StatusInformation(
                                label: $item['_page']['tx_ximatypo3contentplanner_comments'] . ' ' . $GLOBALS['LANG']->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:comments'),
                                severity: \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::NOTICE,
                                icon: 'actions-message',
                            );
                        }
                    } else {
                        $item['backgroundColor'] = Configuration\Colors::get($status->getColor(), true);
                    }
                }
            } else {
                if (version_compare($version, '13.0.0', '>=')) {
                    // Workaround for label behavior in TYPO3 13
                    // Labels will be inherited from parent pages, if not set explicitly
                    // Currently there is no way to suppress this behavior
                    // @see https://github.com/TYPO3/typo3/blob/5619d59f00808f7bec7a311106fda6a52854c0bd/Build/Sources/TypeScript/backend/tree/tree.ts#L1224
                    // @phpstan-ignore-next-line
                    $item['labels'][] = new \TYPO3\CMS\Backend\Dto\Tree\Label\Label(
                        label: '',
                        color: 'inherit',
                    );
                }
            }
        }
        $event->setItems($items);
    }
}
