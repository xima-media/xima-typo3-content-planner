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
            if (isset($item['_page']['tx_ximatypo3contentplanner_status'])) {
                $status = $this->statusRepository->findByUid($item['_page']['tx_ximatypo3contentplanner_status']);
                if ($status) {
                    $version = VersionNumberUtility::getCurrentTypo3Version();
                    if (version_compare($version, '13.0.0', '>=')) {
                        // @phpstan-ignore-next-line
                        $item['labels'][] = new \TYPO3\CMS\Backend\Dto\Tree\Label\Label(
                            label: $status->getTitle(),
                            color: Configuration::STATUS_COLOR_CODES[$status->getColor()],
                            priority: 1,
                        );
                        if (ExtensionUtility::isFeatureEnabled(Configuration::FEATURE_CURRENT_ASSIGNEE_HIGHLIGHT)) {
                            // @phpstan-ignore-next-line
                            $item['statusInformation'][] = new \TYPO3\CMS\Backend\Dto\Tree\Status\StatusInformation(
                                label: $GLOBALS['LANG']->sL('LLL:EXT:' . Configuration::EXT_KEY . '/Resources/Private/Language/locallang_be.xlf:currentAssignee'),
                                severity: \TYPO3\CMS\Core\Type\ContextualFeedbackSeverity::WARNING,
                                icon: 'actions-dot',
                            );
                        }
                    } else {
                        $item['backgroundColor'] = Configuration::STATUS_COLOR_CODES[$status->getColor()];
                    }
                }
            }
        }
        $event->setItems($items);
    }
}
